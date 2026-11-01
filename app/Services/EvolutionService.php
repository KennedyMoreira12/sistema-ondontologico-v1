<?php

namespace App\Services;

use App\Exceptions\EvolutionIndisponivel;
use App\Models\Central\Clinic;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Cliente HTTP da Evolution API.
 *
 * A Evolution autentica pelo header `apikey`, e o que ela aceita é o token da
 * INSTÂNCIA, não a chave global do servidor. Por isso o token vem da clínica e
 * não do .env: uma chave global aqui daria a qualquer chamada autoridade sobre
 * as instâncias de todas as clínicas. É o mesmo token que a Evolution devolve no
 * corpo do webhook, e é ele que permite à rota de entrada decidir se a requisição
 * é da Evolution de verdade — a Evolution não assina o webhook com HMAC.
 *
 * Sem `retry`: reenviar mensagem por conta própria duplica o que o paciente
 * recebe. O retry que existe é o da Evolution no webhook de entrada, e é por isso
 * que a dedupe do lado do Laravel importa.
 */
class EvolutionService
{
    /**
     * Envia texto para um número.
     *
     * O número vem sem o sufixo do JID: a Evolution aceita `5511999999999` ou
     * `5511999999999@s.whatsapp.net`, e o JID cru só funcionaria por tolerância
     * do backend, não por contrato.
     *
     * @param  string  $number  Só dígitos, com código do país.
     * @return array<string, mixed>
     */
    public function sendText(Clinic $clinic, string $number, string $text): array
    {
        $response = $this->request($clinic)
            ->post($this->url("/message/sendText/{$clinic->evolution_instance}"), [
                'number' => $this->normalizarNumero($number),
                'text' => $text,
            ]);

        $response->throw();

        return $this->json($response);
    }

    /**
     * Envia um arquivo por URL, que é o caminho que a Evolution aceita sem
     * upload. A Evolution não adivinha o tipo pelo URL, então `mediaType` e
     * `mimeType` são explícitos: mandar `document` com `image/png` é uma das
     * formas mais comuns de a Evolution devolver 400 sem dizer por quê.
     *
     * @return array<string, mixed>
     */
    public function sendMedia(
        Clinic $clinic,
        string $number,
        string $mediaUrl,
        string $mediaType = 'document',
        string $mimeType = 'application/pdf',
        string $fileName = '',
        string $caption = '',
    ): array {
        $payload = [
            'number' => $this->normalizarNumero($number),
            'mediatype' => $mediaType,
            'mimetype' => $mimeType,
            'url' => $mediaUrl,
        ];

        if ($fileName !== '') {
            $payload['fileName'] = $fileName;
        }

        if ($caption !== '') {
            $payload['caption'] = $caption;
        }

        $response = $this->request($clinic)
            ->post($this->url("/message/sendMedia/{$clinic->evolution_instance}"), $payload);

        $response->throw();

        return $this->json($response);
    }

    /**
     * Envia um menu de botões de resposta rápida.
     *
     * O payload vem de `MenuInterativo::payload()`, que já o traduziu para o
     * schema da Evolution (`buttons[].type`, `displayText`, `id`). Aqui não há
     * validação de negócio: a Evolution 2.3.7 recusa a mensagem inteira quando
     * o formato está errado, e esse erro precisa chegar a quem chamou para o
     * fallback textual entrar.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function sendButtons(Clinic $clinic, string $number, array $payload): array
    {
        $response = $this->request($clinic)
            ->post($this->url("/message/sendButtons/{$clinic->evolution_instance}"), array_merge($payload, [
                'number' => $this->normalizarNumero($number),
            ]));

        $response->throw();

        return $this->json($response);
    }

    /**
     * Envia um menu em lista (uma seção, até dez linhas).
     *
     * `footerText`, `buttonText` e `sections` são obrigatórios no schema da
     * versão instalada; `description` de cada linha é truncada pela própria
     * Evolution em 72 caracteres, então `MenuInterativo` já corta antes.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function sendList(Clinic $clinic, string $number, array $payload): array
    {
        $response = $this->request($clinic)
            ->post($this->url("/message/sendList/{$clinic->evolution_instance}"), array_merge($payload, [
                'number' => $this->normalizarNumero($number),
            ]));

        $response->throw();

        return $this->json($response);
    }

    /**
     * Estado da conexão do WhatsApp da clínica.
     *
     * Serve ao painel para dizer "QR code pendente" em vez de a clínica só
     * descobrir que parou de responder quando alguém tenta marcar consulta.
     *
     * @return array<string, mixed>
     */
    public function connectionState(Clinic $clinic): array
    {
        $response = $this->request($clinic)
            ->get($this->url("/instance/connectionState/{$clinic->evolution_instance}"));

        $response->throw();

        return $this->json($response);
    }

    /**
     * Inicia ou retoma o pareamento do WhatsApp da clínica e devolve o QR.
     *
     * `GET /instance/connect/{instance}` na Evolution 2.x responde conforme o
     * estado da instância e nunca desconecta uma já conectada: instância `open`
     * devolve só o estado; `connecting` devolve o QR corrente; `close` inicia o
     * pareamento e devolve o QR novo. O parâmetro `number` NÃO é enviado de
     * propósito — com ele a Evolution troca o QR por um código de pareamento por
     * telefone, e a tela pede a imagem do QR.
     *
     * @return array<string, mixed>
     */
    public function connect(Clinic $clinic): array
    {
        $response = $this->request($clinic)
            ->get($this->url("/instance/connect/{$clinic->evolution_instance}"));

        $response->throw();

        return $this->json($response);
    }

    /**
     * Base da requisição já com o `apikey` da clínica e o timeout da config.
     */
    protected function request(Clinic $clinic): PendingRequest
    {
        if (! $clinic->hasEvolutionIntegration()) {
            throw EvolutionIndisponivel::semIntegracao($clinic->name);
        }

        if (rtrim((string) config('services.evolution.url'), '/') === '') {
            throw EvolutionIndisponivel::semUrl();
        }

        return Http::timeout((int) config('services.evolution.timeout', 30))
            ->acceptJson()
            ->withHeaders(['apikey' => (string) $clinic->evolution_token]);
    }

    /**
     * URL absoluta de um endpoint.
     *
     * A barra final da config é aparada aqui em vez de confiar em `baseUrl`, para
     * que o caminho da API apareça inteiro na linha e uma barra duplicada seja
     * visível no log em vez de silenciosamente tolerada.
     */
    protected function url(string $path): string
    {
        return rtrim((string) config('services.evolution.url'), '/').'/'.ltrim($path, '/');
    }

    /**
     * Só dígitos. O `@s.whatsapp.net` sai junto, para o `remote_jid` que o
     * chamador gravar na conversa não carregar um sufixo que pertence ao
     * protocolo do WhatsApp e não ao telefone.
     */
    protected function normalizarNumero(string $number): string
    {
        $numero = preg_replace('/\D/', '', $number);

        if ($numero === null || $numero === '') {
            throw new RuntimeException('Número de WhatsApp inválido para envio: '.$number);
        }

        return $numero;
    }

    /**
     * Corpo JSON da resposta, com o erro logado quando a Evolution devolveu 200
     * carregando `error`.
     *
     * A Evolution sinaliza falha por exemplo assim, e devolver esse array para o
     * controller faria ele responder sucesso ao n8n e dar a mensagem como
     * entregue quando ninguém recebeu nada.
     *
     * @return array<string, mixed>
     */
    protected function json(Response $response): array
    {
        $data = $response->json() ?? [];

        if (isset($data['error'])) {
            Log::warning('A Evolution API respondeu com erro no corpo.', [
                'status' => $response->status(),
                'erro' => $data['error'],
                'response' => $data,
            ]);
        }

        return is_array($data) ? $data : [];
    }
}

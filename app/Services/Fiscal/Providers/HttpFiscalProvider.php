<?php

namespace App\Services\Fiscal\Providers;

use App\Contracts\FiscalProviderInterface;
use App\Services\Fiscal\FiscalException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Base dos provedores que falam HTTP.
 *
 * Concentra credenciais, timeouts e tradução de erro para FiscalException,
 * para que cada driver só descreva endpoints e o mapeamento da resposta.
 */
abstract class HttpFiscalProvider implements FiscalProviderInterface
{
    public function __construct(
        protected readonly string $baseUrl,
        protected readonly string $credencial,
    ) {}

    /**
     * @return array<string, string>
     */
    abstract protected function headers(): array;

    protected function http(): PendingRequest
    {
        $this->validarCredenciais();

        return Http::baseUrl(rtrim($this->baseUrl, '/'))
            ->withHeaders($this->headers())
            ->acceptJson()
            ->asJson()
            ->connectTimeout((int) config('fiscal.timeout.connect', 10))
            ->timeout((int) config('fiscal.timeout.request', 30));
    }

    protected function validarCredenciais(): void
    {
        if (trim($this->baseUrl) === '') {
            throw new FiscalException("URL do provedor fiscal [{$this->nome()}] não configurada.");
        }

        if (trim($this->credencial) === '') {
            throw new FiscalException("Credencial do provedor fiscal [{$this->nome()}] não configurada.");
        }
    }

    /**
     * Executa a requisição e traduz falhas em FiscalException.
     *
     * @param  array<string, mixed>  $dados
     * @param  array<string, string>  $headers
     * @return array<string, mixed>
     */
    protected function enviar(string $metodo, string $caminho, array $dados = [], array $headers = []): array
    {
        try {
            $resposta = $this->http()->withHeaders($headers)->{$metodo}($caminho, $dados);

            if ($resposta->failed()) {
                throw new FiscalException(
                    "Provedor [{$this->nome()}] recusou a requisição ({$resposta->status()}).",
                    motivo: $this->mensagemDeErro($resposta->json() ?: $resposta->body()),
                    resposta: (array) ($resposta->json() ?? []),
                );
            }

            return (array) ($resposta->json() ?? []);
        } catch (ConnectionException $e) {
            throw new FiscalException(
                "Não foi possível falar com o provedor [{$this->nome()}]: {$e->getMessage()}",
                previous: $e,
            );
        } catch (RequestException $e) {
            throw new FiscalException(
                "Provedor [{$this->nome()}] retornou erro: {$e->getMessage()}",
                motivo: $this->mensagemDeErro($e->response?->json() ?: $e->getMessage()),
                resposta: (array) ($e->response?->json() ?? []),
                previous: $e,
            );
        }
    }

    /**
     * @param  array<string, mixed>|string  $corpo
     */
    protected function mensagemDeErro(array|string $corpo): string
    {
        if (is_string($corpo)) {
            return mb_substr($corpo, 0, 500);
        }

        $mensagem = $this->valor($corpo, ['mensagem', 'message', 'erro', 'error', 'motivo', 'detail'])
            ?? $this->valor($corpo, ['errors.0.message', 'errors.0.detail', 'errors.0']);

        return $mensagem !== null ? mb_substr($mensagem, 0, 500) : 'Erro não detalhado pelo provedor.';
    }

    /**
     * Primeiro valor não vazio entre os caminhos informados.
     *
     * @param  array<string, mixed>  $dados
     * @param  array<int, string>  $chaves
     */
    protected function valor(array $dados, array $chaves): ?string
    {
        foreach ($chaves as $chave) {
            $valor = data_get($dados, $chave);

            if (is_scalar($valor) && trim((string) $valor) !== '') {
                return (string) $valor;
            }
        }

        return null;
    }

    /**
     * Baixa o XML autorizado, quando o provedor expõe o arquivo à parte.
     */
    protected function baixarXml(string $caminho): ?string
    {
        try {
            $resposta = $this->http()->get($caminho);

            if ($resposta->successful()) {
                $corpo = $resposta->body();

                return str_contains($corpo, '<') ? $corpo : null;
            }
        } catch (ConnectionException|RequestException $e) {
            Log::warning('Falha ao baixar XML fiscal', [
                'provider' => $this->nome(),
                'caminho' => $caminho,
                'erro' => $e->getMessage(),
            ]);
        }

        return null;
    }
}

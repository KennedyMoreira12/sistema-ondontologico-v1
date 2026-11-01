<?php

namespace App\Services\Fiscal;

use App\Contracts\FiscalProviderInterface;
use App\Models\ClinicProfile;
use App\Models\NotaFiscal;
use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Orquestra o ciclo de vida da nota fiscal.
 *
 * É o único ponto que escreve em `notas_fiscais` a partir do módulo fiscal.
 * Os controllers (web e API) só chamam estes métodos; os provedores só falam
 * HTTP. Assim, emissão, cancelamento e sincronização seguem a mesma regra,
 * independentemente do driver configurado.
 */
class NotaFiscalService
{
    public function __construct(
        private readonly FiscalManager $manager,
        private readonly PayloadBuilder $payloads = new PayloadBuilder,
        private readonly XmlBuilder $xmlBuilder = new XmlBuilder,
    ) {}

    public function provider(): FiscalProviderInterface
    {
        return $this->manager->provider();
    }

    /**
     * Provedor que deve responder por esta nota.
     *
     * Cancelar e consultar têm de ir para quem emitiu. Trocar FISCAL_PROVIDER
     * não pode fazer o sistema consultar uma nota antiga na API errada; notas
     * anteriores ao módulo, sem `provider`, caem no driver padrão.
     */
    private function providerDaNota(NotaFiscal $nota): FiscalProviderInterface
    {
        return $this->manager->provider($nota->provider ?: null);
    }

    public function providerNome(): string
    {
        return $this->manager->getDefaultDriver();
    }

    /**
     * Emite a nota junto ao provedor configurado.
     *
     * Não lança exceção por recusa do provedor: grava o motivo e devolve a
     * nota com status `rejeitada`, para a tela mostrar o erro sem 500.
     */
    public function emitir(NotaFiscal $nota): NotaFiscal
    {
        if (! $nota->podeEmitir()) {
            throw new FiscalException('Esta nota não pode ser emitida no status atual.');
        }

        if ((float) $nota->valor_total <= 0) {
            throw new FiscalException('Informe um valor maior que zero antes de emitir.');
        }

        $this->garantirEmitenteConfigurado();

        $provider = $this->providerNome();

        $nota = DB::transaction(function () use ($nota, $provider) {
            /** @var NotaFiscal $travada */
            $travada = NotaFiscal::query()->lockForUpdate()->findOrFail($nota->getKey());

            $travada->forceFill([
                'numero' => $travada->numero ?: NotaFiscal::proximoNumero(),
                'serie' => $travada->serie ?: (string) config('fiscal.serie', '1'),
                'ambiente' => $travada->ambiente ?: config('fiscal.ambiente', 'homologacao'),
                'provider' => $provider,
                'status' => 'processando',
                'motivo_rejeicao' => null,
                'tentativas' => (int) $travada->tentativas + 1,
                'ultima_tentativa_em' => Carbon::now(),
            ])->save();

            return $travada;
        });

        $payload = $this->payloads->build($nota);

        try {
            $resultado = $this->provider()->emitir($nota, $payload);
        } catch (FiscalException $e) {
            return $this->marcarRejeitada($nota, $e->motivo ?? $e->getMessage());
        } catch (\Throwable $e) {
            Log::error('Erro inesperado na emissão fiscal', ['nota' => $nota->id, 'erro' => $e]);

            return $this->marcarRejeitada($nota, 'Erro inesperado: '.$e->getMessage());
        }

        if (! $resultado->sucesso) {
            return $this->marcarRejeitada($nota, $resultado->mensagem ?? 'Nota rejeitada pelo provedor.');
        }

        $nota->forceFill([
            'status' => $resultado->status,
            'chave' => $resultado->chave ?: $nota->chave,
            'numero' => $resultado->numero ?: $nota->numero,
            'serie' => $resultado->serie ?: $nota->serie,
            'protocolo' => $resultado->protocolo ?: $nota->protocolo,
            'emitida_em' => $nota->emitida_em ?: Carbon::now(),
            'link_pdf' => $resultado->pdfUrl,
            'dados' => array_merge((array) $nota->dados, [
                'provider' => $this->providerNome(),
                'resposta' => $resultado->resposta,
            ]),
        ]);

        if ($resultado->xml) {
            $this->salvarXml($nota, $resultado->xml);
        }

        $nota->link_xml = route('fiscal.xml', $nota);
        $nota->link_pdf = $nota->link_pdf ?: route('fiscal.imprimir', $nota);
        $nota->save();

        AuditLogger::log('emitir', 'notas_fiscais', $nota->id, depois: [
            'status' => $nota->status,
            'chave' => $nota->chave,
            'numero' => $nota->numero,
            'provider' => $this->providerNome(),
        ]);

        return $nota->refresh();
    }

    /**
     * Cancela a nota autorizada junto ao provedor.
     */
    public function cancelar(NotaFiscal $nota, string $motivo): NotaFiscal
    {
        if (! $nota->podeCancelar()) {
            throw new FiscalException('Apenas notas autorizadas podem ser canceladas.');
        }

        try {
            $resultado = $this->providerDaNota($nota)->cancelar($nota, $motivo);

            if (! $resultado->sucesso) {
                throw new FiscalException($resultado->mensagem ?? 'Cancelamento recusado pelo provedor.');
            }

            $nota->forceFill([
                'status' => 'cancelada',
                'cancelada_em' => Carbon::now(),
                'motivo_cancelamento' => $motivo,
                'protocolo' => $resultado->protocolo ?: $nota->protocolo,
                'dados' => array_merge((array) $nota->dados, [
                    'cancelamento' => $resultado->resposta,
                ]),
            ])->save();
        } catch (FiscalException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('Erro inesperado no cancelamento fiscal', ['nota' => $nota->id, 'erro' => $e]);

            throw new FiscalException('Não foi possível cancelar: '.$e->getMessage(), previous: $e);
        }

        AuditLogger::log('cancelar', 'notas_fiscais', $nota->id, depois: [
            'motivo' => $motivo,
            'status' => $nota->status,
        ]);

        return $nota->refresh();
    }

    /**
     * Atualiza o status de uma nota já transmitida.
     */
    public function consultar(NotaFiscal $nota): NotaFiscal
    {
        if ($nota->status === 'rascunho') {
            throw new FiscalException('Rascunho ainda não foi transmitido.');
        }

        try {
            $resultado = $this->providerDaNota($nota)->consultar($nota);
        } catch (FiscalException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new FiscalException('Não foi possível consultar a nota: '.$e->getMessage(), previous: $e);
        }

        if (! $resultado->sucesso) {
            throw new FiscalException($resultado->mensagem ?? 'O provedor não devolveu o status da nota.');
        }

        $antes = $nota->status;

        $nota->forceFill([
            'status' => $resultado->status,
            'chave' => $resultado->chave ?: $nota->chave,
            'numero' => $resultado->numero ?: $nota->numero,
            'serie' => $resultado->serie ?: $nota->serie,
            'protocolo' => $resultado->protocolo ?: $nota->protocolo,
        ]);

        if ($resultado->status === 'autorizada' && ! $nota->emitida_em) {
            $nota->emitida_em = Carbon::now();
        }

        if ($resultado->xml) {
            $this->salvarXml($nota, $resultado->xml);
        }

        $nota->save();

        if ($antes !== $nota->status) {
            AuditLogger::log('sincronizar', 'notas_fiscais', $nota->id, antes: ['status' => $antes], depois: ['status' => $nota->status]);
        }

        return $nota->refresh();
    }

    /**
     * XML da nota: o autorizado, quando já foi gravado; senão, o local.
     */
    public function xml(NotaFiscal $nota): string
    {
        $caminho = $this->caminhoXml($nota);

        if (Storage::disk('local')->exists($caminho)) {
            return (string) Storage::disk('local')->get($caminho);
        }

        if ($xml = data_get($nota->dados, 'xml')) {
            return (string) $xml;
        }

        return $this->xmlBuilder->build($this->payloads->build($nota), $nota);
    }

    /**
     * Notas que ainda podem mudar de status, para o comando de sincronização.
     */
    public function sincronizaveis(): Builder
    {
        return NotaFiscal::query()
            ->whereIn('status', ['processando', 'autorizada'])
            ->whereNotNull('emitida_em');
    }

    private function salvarXml(NotaFiscal $nota, string $xml): void
    {
        if (! str_contains($xml, '<')) {
            return;
        }

        Storage::disk('local')->put($this->caminhoXml($nota), $xml);

        $nota->dados = array_merge((array) $nota->dados, ['xml' => $xml]);
    }

    private function caminhoXml(NotaFiscal $nota): string
    {
        return "fiscal/xml/nota-{$nota->getKey()}.xml";
    }

    private function marcarRejeitada(NotaFiscal $nota, string $motivo): NotaFiscal
    {
        $nota->forceFill([
            'status' => 'rejeitada',
            'motivo_rejeicao' => mb_substr($motivo, 0, 1000),
            'emitida_em' => null,
        ])->save();

        AuditLogger::log('rejeitar', 'notas_fiscais', $nota->id, depois: [
            'motivo' => $nota->motivo_rejeicao,
            'provider' => $this->providerNome(),
        ]);

        return $nota->refresh();
    }

    /**
     * Emitente precisa de CNPJ/CPF e nome para qualquer provedor real.
     */
    private function garantirEmitenteConfigurado(): void
    {
        $perfil = ClinicProfile::query()->first();
        $documento = ChaveAcesso::somenteDigitos($perfil?->document);

        if (blank($perfil?->legal_name)) {
            throw new FiscalException('Preencha a razão social da clínica em Configurações da clínica antes de emitir.');
        }

        if (! in_array(strlen($documento), [11, 14], true)) {
            throw new FiscalException('Preencha o CNPJ (ou CPF) da clínica em Configurações da clínica antes de emitir.');
        }
    }
}

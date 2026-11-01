<?php

namespace App\Http\Resources;

use App\Models\NotaFiscal;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin NotaFiscal
 */
class NotaFiscalResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'numero' => $this->numero,
            'serie' => $this->serie,
            'chave' => $this->chave,
            'protocolo' => $this->protocolo,
            'tipo' => $this->tipo,
            'status' => $this->status,
            'status_label' => $this->statusLabel(),
            'ambiente' => $this->ambiente,
            'provider' => $this->provider,
            'paciente' => $this->whenLoaded('paciente', fn () => [
                'id' => $this->paciente?->id,
                'nome' => $this->paciente?->nome,
                'cpf' => $this->paciente?->cpf,
            ]),
            'consulta_id' => $this->consulta_id,
            'tomador' => [
                'nome' => $this->tomador_nome,
                'documento' => $this->tomador_documento,
                'email' => $this->tomador_email,
                'endereco' => $this->tomador_endereco,
                'cidade' => $this->tomador_cidade,
                'uf' => $this->tomador_uf,
                'cep' => $this->tomador_cep,
            ],
            'servico' => [
                'codigo' => $this->codigo_servico,
                'descricao' => $this->descricao_servico,
            ],
            'valores' => [
                'total' => (float) $this->valor_total,
                'desconto' => (float) $this->valor_desconto,
                'liquido' => $this->valorLiquido(),
                'iss' => (float) $this->valor_iss,
                'aliquota_iss' => (float) $this->aliquota_iss,
            ],
            'motivo_rejeicao' => $this->motivo_rejeicao,
            'motivo_cancelamento' => $this->motivo_cancelamento,
            'tentativas' => $this->tentativas,
            'links' => [
                // Na API o link tem de ser consumível sem sessão: as rotas
                // `fiscal.*` são do painel e exigem login. Já o PDF só existe
                // na tela (não há dompdf), então fica de fora do JSON da API.
                'xml' => $request->is('api/*')
                    ? route('api.fiscal.notas.xml', $this->resource)
                    : route('fiscal.xml', $this->resource),
                'pdf' => $this->link_pdf ?: ($request->is('api/*') ? null : route('fiscal.imprimir', $this->resource)),
            ],
            'emitida_em' => $this->emitida_em?->toIso8601String(),
            'cancelada_em' => $this->cancelada_em?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}

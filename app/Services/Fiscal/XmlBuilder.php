<?php

namespace App\Services\Fiscal;

use App\Models\NotaFiscal;
use DOMDocument;
use DOMElement;

/**
 * XML do documento fiscal para guarda e download.
 *
 * Quando o provedor devolve o XML oficial (`FiscalResultado::$xml`), esse é o
 * arquivo gravado. Este builder é o fallback: gera um XML estruturado com os
 * mesmos dados da nota, útil para impressão, conferência e auditoria — não
 * substitui o XML autorizado pela prefeitura/SEFAZ.
 */
class XmlBuilder
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function build(array $payload, ?NotaFiscal $nota = null): string
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        $root = $dom->createElement('notaFiscal');
        $root->setAttribute('versao', '1.0');
        $root->setAttribute('ambiente', (string) ($payload['ambiente'] ?? 'homologacao'));
        $root->setAttribute('tipo', (string) ($payload['tipo'] ?? 'nfse'));

        if ($nota?->chave) {
            $root->setAttribute('chave', $nota->chave);
        }

        $dom->appendChild($root);

        $this->append($dom, $root, 'identificacao', [
            'numero' => $payload['numero'] ?? null,
            'serie' => $payload['serie'] ?? null,
            'dataEmissao' => $payload['data_emissao'] ?? null,
            'protocolo' => $nota?->protocolo,
            'status' => $nota?->status,
        ]);

        $prestador = (array) ($payload['prestador'] ?? []);
        $this->append($dom, $root, 'prestador', [
            'cnpj' => $prestador['cnpj'] ?? null,
            'razaoSocial' => $prestador['razao_social'] ?? null,
            'inscricaoMunicipal' => $prestador['inscricao_municipal'] ?? null,
            'inscricaoEstadual' => $prestador['inscricao_estadual'] ?? null,
            'email' => $prestador['email'] ?? null,
            'telefone' => $prestador['telefone'] ?? null,
            'cnae' => $prestador['cnae'] ?? null,
            'regimeTributario' => $prestador['regime_tributario'] ?? null,
            'endereco' => $prestador['endereco'] ?? [],
        ]);

        $tomador = (array) ($payload['tomador'] ?? []);
        $this->append($dom, $root, 'tomador', [
            'nome' => $tomador['nome'] ?? null,
            'documento' => $tomador['documento'] ?? null,
            'email' => $tomador['email'] ?? null,
            'endereco' => $tomador['endereco'] ?? [],
        ]);

        $servico = (array) ($payload['servico'] ?? []);
        $this->append($dom, $root, 'servico', [
            'codigo' => $servico['codigo'] ?? null,
            'descricao' => $servico['descricao'] ?? null,
            'aliquotaIss' => $servico['aliquota_iss'] ?? null,
        ]);

        $valores = (array) ($payload['valores'] ?? []);
        $this->append($dom, $root, 'valores', [
            'servico' => $valores['servico'] ?? null,
            'desconto' => $valores['desconto'] ?? null,
            'liquido' => $valores['liquido'] ?? null,
            'iss' => $valores['iss'] ?? null,
        ]);

        if ($nota?->motivo_rejeicao) {
            $this->append($dom, $root, 'rejeicao', ['motivo' => $nota->motivo_rejeicao]);
        }

        if ($nota?->motivo_cancelamento) {
            $this->append($dom, $root, 'cancelamento', [
                'motivo' => $nota->motivo_cancelamento,
                'data' => $nota->cancelada_em?->toIso8601String(),
            ]);
        }

        return (string) $dom->saveXML();
    }

    /**
     * @param  array<string, mixed>  $dados
     */
    private function append(DOMDocument $dom, DOMElement $parent, string $nome, array $dados): void
    {
        $elemento = $dom->createElement($nome);

        foreach ($dados as $chave => $valor) {
            if ($valor === null || $valor === '') {
                continue;
            }

            if (is_array($valor)) {
                $this->append($dom, $elemento, (string) $chave, $valor);

                continue;
            }

            $filho = $dom->createElement((string) $chave);
            $filho->appendChild($dom->createTextNode($this->texto($valor)));
            $elemento->appendChild($filho);
        }

        $parent->appendChild($elemento);
    }

    private function texto(mixed $valor): string
    {
        if (is_bool($valor)) {
            return $valor ? 'true' : 'false';
        }

        if (is_float($valor)) {
            return number_format($valor, 2, '.', '');
        }

        return (string) $valor;
    }
}

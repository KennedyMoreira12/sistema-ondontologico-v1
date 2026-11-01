@php
    $emitente = \App\Models\ClinicProfile::query()->first();
    $homologacao = ($notaFiscal->ambiente ?? config('fiscal.ambiente')) !== 'producao';
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Nota fiscal {{ $notaFiscal->numero ?? '#'.$notaFiscal->id }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: -apple-system, "Segoe UI", Roboto, Arial, sans-serif; color: #111; margin: 0; padding: 24px; background: #f3f4f6; }
        .folha { background: #fff; max-width: 820px; margin: 0 auto; padding: 32px; border: 1px solid #d1d5db; }
        h1 { font-size: 18px; margin: 0; }
        .topo { display: flex; justify-content: space-between; gap: 24px; border-bottom: 2px solid #111; padding-bottom: 16px; }
        .topo .emitente { font-size: 12px; line-height: 1.5; }
        .topo .titulo { text-align: right; }
        .topo .titulo small { display: block; color: #555; font-size: 11px; }
        .badge { display: inline-block; border: 1px solid #111; border-radius: 999px; padding: 2px 10px; font-size: 11px; text-transform: uppercase; letter-spacing: .04em; }
        .grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px 16px; margin-top: 20px; }
        .campo { font-size: 11px; }
        .campo span { display: block; color: #555; text-transform: uppercase; letter-spacing: .04em; font-size: 10px; margin-bottom: 2px; }
        .campo strong { font-size: 13px; font-weight: 600; }
        .secao { margin-top: 24px; }
        .secao h2 { font-size: 12px; text-transform: uppercase; letter-spacing: .06em; border-bottom: 1px solid #d1d5db; padding-bottom: 6px; margin: 0 0 12px; color: #374151; }
        table { width: 100%; border-collapse: collapse; font-size: 12px; }
        th, td { text-align: left; padding: 8px; border-bottom: 1px solid #e5e7eb; }
        th { background: #f9fafb; font-size: 11px; text-transform: uppercase; letter-spacing: .04em; color: #4b5563; }
        td.num, th.num { text-align: right; }
        .total { font-size: 15px; font-weight: 700; }
        .chave { margin-top: 20px; padding: 12px; border: 1px dashed #9ca3af; font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 11px; word-break: break-all; }
        .aviso { margin-top: 24px; font-size: 11px; color: #6b7280; text-align: center; }
        .acoes { max-width: 820px; margin: 0 auto 16px; display: flex; justify-content: flex-end; gap: 8px; }
        .acoes button, .acoes a { font: inherit; font-size: 13px; padding: 8px 14px; border-radius: 6px; border: 1px solid #374151; background: #fff; cursor: pointer; text-decoration: none; color: #111; }
        .acoes button { background: #111; color: #fff; }
        @media print {
            body { background: #fff; padding: 0; }
            .folha { border: 0; max-width: none; padding: 0; }
            .acoes { display: none; }
        }
    </style>
</head>
<body>
    <div class="acoes">
        <a href="{{ route('fiscal.show', $notaFiscal) }}">Voltar</a>
        <button type="button" onclick="window.print()">Imprimir</button>
    </div>

    <div class="folha">
        <div class="topo">
            <div class="emitente">
                <h1>{{ $emitente?->legal_name ?? config('app.name') }}</h1>
                <div>
                    @if ($emitente?->document)
                        CNPJ/CPF: {{ $emitente->document }}<br>
                    @endif
                    @if ($emitente?->municipal_registration)
                        Inscrição municipal: {{ $emitente->municipal_registration }}<br>
                    @endif
                    @if ($emitente?->address)
                        {{ $emitente->address }}{{ $emitente->number ? ', '.$emitente->number : '' }}
                        {{ $emitente->complement ? ' — '.$emitente->complement : '' }}<br>
                        {{ $emitente->district ? $emitente->district.' · ' : '' }}{{ $emitente->city }}{{ $emitente->state ? '/'.$emitente->state : '' }}
                        {{ $emitente->postal_code ? ' · CEP '.$emitente->postal_code : '' }}
                    @endif
                </div>
            </div>
            <div class="titulo">
                <span class="badge">{{ strtoupper($notaFiscal->tipo) }}</span>
                <h1>Nota fiscal de serviço</h1>
                <small>Número {{ $notaFiscal->numero ?? '—' }} · Série {{ $notaFiscal->serie ?? '—' }}</small>
                <small>Status: {{ $notaFiscal->statusLabel() }}</small>
            </div>
        </div>

        <div class="grid">
            <div class="campo"><span>Emissão</span><strong>{{ $notaFiscal->emitida_em?->format('d/m/Y H:i') ?? now()->format('d/m/Y H:i') }}</strong></div>
            <div class="campo"><span>Ambiente</span><strong>{{ ucfirst($notaFiscal->ambiente ?? config('fiscal.ambiente')) }}</strong></div>
            <div class="campo"><span>Provedor</span><strong>{{ $notaFiscal->provider ?? '—' }}</strong></div>
            <div class="campo"><span>Protocolo</span><strong>{{ $notaFiscal->protocolo ?? '—' }}</strong></div>
        </div>

        <div class="secao">
            <h2>Tomador do serviço</h2>
            <div class="grid">
                <div class="campo" style="grid-column: span 2;">
                    <span>Nome / razão social</span>
                    <strong>{{ $notaFiscal->tomador_nome ?: ($notaFiscal->paciente?->nome ?? '—') }}</strong>
                </div>
                <div class="campo"><span>CPF / CNPJ</span><strong>{{ $notaFiscal->tomador_documento ?: ($notaFiscal->paciente?->cpf ?? '—') }}</strong></div>
                <div class="campo"><span>E-mail</span><strong>{{ $notaFiscal->tomador_email ?: ($notaFiscal->paciente?->email ?? '—') }}</strong></div>
                <div class="campo" style="grid-column: span 2;">
                    <span>Endereço</span>
                    <strong>{{ $notaFiscal->tomador_endereco ?: ($notaFiscal->paciente?->endereco ?? '—') }}</strong>
                </div>
                <div class="campo"><span>Cidade / UF</span><strong>{{ $notaFiscal->tomador_cidade ?: ($notaFiscal->paciente?->cidade ?? '—') }}{{ ($notaFiscal->tomador_uf ?: $notaFiscal->paciente?->estado) ? '/'.($notaFiscal->tomador_uf ?: $notaFiscal->paciente?->estado) : '' }}</strong></div>
                <div class="campo"><span>CEP</span><strong>{{ $notaFiscal->tomador_cep ?: ($notaFiscal->paciente?->cep ?? '—') }}</strong></div>
            </div>
        </div>

        <div class="secao">
            <h2>Serviço prestado</h2>
            <table>
                <thead>
                    <tr>
                        <th>Código</th>
                        <th>Descrição</th>
                        <th class="num">Valor</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>{{ $notaFiscal->codigo_servico ?? config('fiscal.codigo_servico') }}</td>
                        <td>{{ $notaFiscal->descricao_servico ?? config('fiscal.descricao_servico') }}</td>
                        <td class="num">R$ {{ number_format($notaFiscal->valor_total, 2, ',', '.') }}</td>
                    </tr>
                </tbody>
            </table>

            <table style="margin-top: 16px;">
                <tbody>
                    <tr>
                        <td>Valor do serviço</td>
                        <td class="num">R$ {{ number_format($notaFiscal->valor_total, 2, ',', '.') }}</td>
                    </tr>
                    <tr>
                        <td>Desconto</td>
                        <td class="num">R$ {{ number_format($notaFiscal->valor_desconto ?? 0, 2, ',', '.') }}</td>
                    </tr>
                    <tr>
                        <td>ISS ({{ number_format($notaFiscal->aliquota_iss ?? 0, 2, ',', '.') }}%)</td>
                        <td class="num">R$ {{ number_format($notaFiscal->valor_iss ?? 0, 2, ',', '.') }}</td>
                    </tr>
                    <tr>
                        <td class="total">Valor líquido</td>
                        <td class="num total">R$ {{ number_format($notaFiscal->valorLiquido(), 2, ',', '.') }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        @if ($notaFiscal->chave)
            <div class="chave">
                <strong>Chave de acesso:</strong> {{ $notaFiscal->chave }}
            </div>
        @endif

        <p class="aviso">
            @if ($homologacao)
                Documento emitido em ambiente de homologação — sem validade fiscal.
            @else
                Documento auxiliar da nota fiscal de serviço. A validade fiscal é a do documento autorizado pelo município.
            @endif
        </p>
    </div>
</body>
</html>

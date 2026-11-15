<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Termo de responsabilidade — {{ $contrato->paciente?->nome }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1f2933; margin: 28px 34px; line-height: 1.5; }
        .header { border-bottom: 2px solid #0b7285; padding-bottom: 10px; margin-bottom: 18px; }
        .header h1 { font-size: 17px; margin: 0 0 2px; color: #0b7285; }
        .header p { margin: 0; color: #52606d; font-size: 10px; }
        h2 { font-size: 14px; margin: 0 0 10px; }
        .meta { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        .meta td { padding: 4px 6px; border-bottom: 1px solid #e4e7eb; vertical-align: top; }
        .meta td.label { width: 150px; color: #52606d; }
        .content { white-space: pre-wrap; text-align: justify; border: 1px solid #e4e7eb; border-radius: 4px; padding: 14px; background: #fbfcfd; }
        .signature { margin-top: 26px; text-align: center; page-break-inside: avoid; }
        .signature img { max-width: 340px; max-height: 150px; }
        .signature-line { border-top: 1px solid #52606d; width: 340px; margin: 2px auto 4px; }
        .signature-name { font-weight: bold; }
        .evidence { margin-top: 22px; page-break-inside: avoid; }
        .evidence table { width: 100%; border-collapse: collapse; }
        .evidence th, .evidence td { border: 1px solid #e4e7eb; padding: 5px 6px; text-align: left; font-size: 9px; }
        .evidence th { background: #f0f4f8; width: 150px; color: #52606d; }
        .hash { font-family: DejaVu Sans Mono, monospace; font-size: 8px; word-break: break-all; }
        .notice { margin-top: 18px; border: 1px solid #f0b429; background: #fffaeb; padding: 9px 11px; font-size: 9px; color: #7c5c00; }
        .footer { margin-top: 20px; border-top: 1px solid #e4e7eb; padding-top: 8px; font-size: 8px; color: #7b8794; text-align: center; }
        .pending { margin-top: 26px; border: 1px dashed #9aa5b1; padding: 18px; text-align: center; color: #7b8794; }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $clinica?->name ?? config('app.name') }}</h1>
        <p>{{ $contrato->tipo === \App\Models\Contract::TIPO_TERMO_RESPONSABILIDADE ? 'Termo de responsabilidade' : ucfirst($contrato->tipo) }} — versão {{ $contrato->versao }}</p>
    </div>

    <h2>Identificação do paciente</h2>
    <table class="meta">
        <tr><td class="label">Nome</td><td>{{ $contrato->paciente?->nome ?? '—' }}</td></tr>
        <tr><td class="label">CPF</td><td>{{ $contrato->paciente?->cpf ?? '—' }}</td></tr>
        <tr><td class="label">RG</td><td>{{ $contrato->paciente?->rg ?? '—' }}</td></tr>
        <tr><td class="label">Nascimento</td><td>{{ $contrato->paciente?->nascimento?->format('d/m/Y') ?? '—' }}</td></tr>
        <tr><td class="label">Documento</td><td>Contrato #{{ $contrato->id }} — {{ $contrato->tipo }}</td></tr>
    </table>

    <h2>Conteúdo do termo</h2>
    <div class="content">{{ $contrato->conteudo ?: 'Sem conteúdo registrado.' }}</div>

    @if ($assinatura)
        <div class="signature">
            <img src="{{ $assinatura->dataUri() }}" alt="Assinatura manuscrita de {{ $contrato->paciente?->nome }}">
            <div class="signature-line"></div>
            <div class="signature-name">{{ $contrato->paciente?->nome ?? 'Paciente' }}</div>
            <div>{{ $assinatura->signed_at?->format('d/m/Y H:i') }}</div>
        </div>

        <div class="evidence">
            <h2>Evidências da assinatura</h2>
            <table>
                <tr><th>Assinado em</th><td>{{ $assinatura->signed_at?->format('d/m/Y H:i:s') }}</td></tr>
                <tr><th>Registrado por</th><td>{{ $assinatura->user?->name ?? '—' }}</td></tr>
                <tr><th>Versão do termo</th><td>{{ $assinatura->term_version }}</td></tr>
                <tr><th>Hash do conteúdo</th><td class="hash">{{ $assinatura->content_hash }}</td></tr>
                <tr><th>Hash da assinatura</th><td class="hash">{{ $assinatura->signature_hash }}</td></tr>
                <tr><th>Integridade</th><td>{{ $assinatura->integra() ? 'Conferida — desenho e conteúdo batem com os hashes registrados.' : 'Divergência detectada — verifique o registro original.' }}</td></tr>
                <tr><th>IP de origem</th><td>{{ $assinatura->ip ?? '—' }}</td></tr>
            </table>
        </div>
    @else
        <div class="pending">Documento ainda não assinado pelo paciente.</div>
    @endif

    <div class="notice">
        Este documento registra uma assinatura manuscrita eletrônica com evidências de integridade.
        Não é uma assinatura digital qualificada ICP-Brasil.
    </div>

    <div class="footer">
        Documento gerado em {{ now()->format('d/m/Y H:i') }} por {{ $clinica?->name ?? config('app.name') }}.
    </div>
</body>
</html>

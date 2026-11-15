<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Ficha do paciente — {{ $paciente->nome }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10.5px; color: #1f2933; margin: 26px 32px; line-height: 1.45; }
        .header { border-bottom: 2px solid #0b7285; padding-bottom: 9px; margin-bottom: 16px; }
        .header h1 { font-size: 16px; margin: 0 0 2px; color: #0b7285; }
        .header p { margin: 0; color: #52606d; font-size: 9px; }
        h2 { font-size: 12.5px; margin: 18px 0 8px; color: #0b7285; border-bottom: 1px solid #e4e7eb; padding-bottom: 3px; }
        .meta { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        .meta td { padding: 3px 5px; border-bottom: 1px solid #eef1f4; vertical-align: top; }
        .meta td.label { width: 130px; color: #52606d; }
        .box { border: 1px solid #e4e7eb; border-radius: 4px; padding: 9px 11px; margin-bottom: 9px; page-break-inside: avoid; }
        .box .when { color: #7b8794; font-size: 9px; }
        .box .body { margin-top: 4px; white-space: pre-wrap; text-align: justify; }
        .signature { margin-top: 12px; text-align: center; page-break-inside: avoid; }
        .signature img { max-width: 280px; max-height: 110px; }
        .signature-line { border-top: 1px solid #52606d; width: 280px; margin: 2px auto 3px; }
        .signature-name { font-weight: bold; font-size: 10px; }
        .evidence { font-size: 8.5px; color: #52606d; margin-top: 5px; }
        .hash { font-family: DejaVu Sans Mono, monospace; font-size: 7.5px; word-break: break-all; }
        .notice { margin-top: 16px; border: 1px solid #f0b429; background: #fffaeb; padding: 8px 10px; font-size: 8.5px; color: #7c5c00; }
        .footer { margin-top: 18px; border-top: 1px solid #e4e7eb; padding-top: 7px; font-size: 8px; color: #7b8794; text-align: center; }
        .muted { color: #7b8794; }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $clinica?->name ?? config('app.name') }}</h1>
        <p>Ficha do paciente — documento gerado em {{ now()->format('d/m/Y H:i') }}</p>
    </div>

    <h2>Identificação</h2>
    <table class="meta">
        <tr><td class="label">Nome</td><td>{{ $paciente->nome }}</td></tr>
        <tr><td class="label">CPF</td><td>{{ $paciente->cpf ?? '—' }}</td></tr>
        <tr><td class="label">RG</td><td>{{ $paciente->rg ?? '—' }}</td></tr>
        <tr><td class="label">Nascimento</td><td>{{ $paciente->nascimento?->format('d/m/Y') ?? '—' }}</td></tr>
        <tr><td class="label">Telefone</td><td>{{ $paciente->celular ?? $paciente->phone ?? '—' }}</td></tr>
    </table>

    <h2>Termo de responsabilidade</h2>
    @if ($termo)
        <div class="box">
            <div class="muted">Versão {{ $termo->versao }} — status: {{ $termo->status }}</div>
            <div class="body">{{ $termo->conteudo ?: 'Sem conteúdo registrado.' }}</div>
        </div>

        @if ($assinaturaTermo)
            <div class="signature">
                <img src="{{ $assinaturaTermo->dataUri() }}" alt="Assinatura manuscrita de {{ $paciente->nome }}">
                <div class="signature-line"></div>
                <div class="signature-name">{{ $paciente->nome }}</div>
                <div>{{ $assinaturaTermo->signed_at?->format('d/m/Y H:i') }}</div>
                <div class="evidence">
                    Responsável: {{ $assinaturaTermo->user?->name ?? '—' }} ·
                    Versão {{ $assinaturaTermo->term_version }} ·
                    {{ $assinaturaTermo->integra() ? 'Integridade conferida' : 'Divergência detectada' }}<br>
                    <span class="hash">Conteúdo: {{ $assinaturaTermo->content_hash }}</span><br>
                    <span class="hash">Assinatura: {{ $assinaturaTermo->signature_hash }}</span>
                </div>
            </div>
        @else
            <p class="muted">Termo ainda não assinado.</p>
        @endif
    @else
        <p class="muted">Nenhum termo de responsabilidade registrado.</p>
    @endif

    <h2>Histórico clínico</h2>
    @forelse ($paciente->evolucoes as $evolucao)
        <div class="box">
            <div class="when">
                {{ $evolucao->created_at?->format('d/m/Y H:i') }} ·
                {{ ucfirst($evolucao->tipo) }} ·
                {{ $evolucao->profissional?->nome ?? 'Profissional não informado' }}
            </div>
            <div class="body">{{ $evolucao->conteudo }}</div>
        </div>
    @empty
        <p class="muted">Nenhum registro clínico.</p>
    @endforelse

    <h2>Sessões e confirmações de presença</h2>
    @forelse ($paciente->evolucoesSessao as $sessao)
        <div class="box">
            <div class="when">
                Sessão de {{ $sessao->data?->format('d/m/Y') }} ·
                {{ $sessao->profissional?->nome ?? 'Profissional não informado' }} ·
                {{ $sessao->estaFinalizada() ? 'Finalizada' : 'Rascunho' }}
            </div>
            <div class="body">{{ $sessao->conteudo }}</div>

            @if ($sessao->assinaturaAtiva)
                <div class="signature">
                    <img src="{{ $sessao->assinaturaAtiva->dataUri() }}" alt="Assinatura de presença de {{ $paciente->nome }}">
                    <div class="signature-line"></div>
                    <div class="signature-name">{{ $paciente->nome }}</div>
                    <div>{{ $sessao->assinaturaAtiva->signed_at?->format('d/m/Y H:i') }}</div>
                    <div class="evidence">
                        Presença confirmada por assinatura ·
                        Responsável: {{ $sessao->assinaturaAtiva->user?->name ?? '—' }} ·
                        {{ $sessao->assinaturaAtiva->integra() ? 'Integridade conferida' : 'Divergência detectada' }}
                    </div>
                </div>
            @else
                <p class="muted">Presença não assinada.</p>
            @endif
        </div>
    @empty
        <p class="muted">Nenhuma sessão registrada.</p>
    @endforelse

    <div class="notice">
        As assinaturas acima são manuscritas eletrônicas, com hash do desenho e do conteúdo assinado.
        Não são assinaturas digitais qualificadas ICP-Brasil.
    </div>

    <div class="footer">
        {{ $clinica?->name ?? config('app.name') }} — ficha gerada pelo sistema em {{ now()->format('d/m/Y H:i') }}.
    </div>
</body>
</html>

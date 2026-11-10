<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>@yield('titulo', 'Documento — Clínica Malheiro')</title>

    {{-- O CSS do documento é compilado de resources/scss/documents/malheiro.scss
         para resources/css/documents/malheiro.css (feito para o Dompdf: sem
         flexbox, sem variáveis CSS, tabela em vez de flex). --}}
    <style>
        {{ file_exists(resource_path('css/documents/malheiro.css')) ? file_get_contents(resource_path('css/documents/malheiro.css')) : '' }}
    </style>
</head>
<body>
    <div class="malheiro-document">
        {{-- CABEÇALHO --}}
        <header class="malheiro-header">
            <table class="malheiro-header__table" width="100%">
                <tr>
                    <td class="malheiro-header__logo">
                        @if (! empty($logo_data_uri))
                            <img src="{{ $logo_data_uri }}" alt="{{ $clinica_nome ?? 'Clínica Malheiro' }}" width="110">
                        @elseif (! empty($logo_url))
                            <img src="{{ $logo_url }}" alt="{{ $clinica_nome ?? 'Clínica Malheiro' }}" width="110">
                        @else
                            <div class="malheiro-badge">MALHEIRO</div>
                        @endif
                    </td>
                    <td class="malheiro-header__identity">
                        <div class="malheiro-header__tagline">Clínicas Especializadas</div>
                        <div class="malheiro-header__name">MALHEIRO</div>
                        <div class="malheiro-header__subtitle">ODONTOLOGIA ESTÉTICA</div>
                    </td>
                </tr>
            </table>

            <table class="malheiro-header__professional" width="100%">
                <tr>
                    <td class="malheiro-header__professional-item">
                        <span class="malheiro-header__professional-label">Profissional:</span>
                        <span class="malheiro-header__professional-value">{{ $nome_profissional ?? 'Dr. Alessandro Agostinho Malheiro' }}</span>
                    </td>
                    <td class="malheiro-header__professional-item">
                        <span class="malheiro-header__professional-label">CRO:</span>
                        <span class="malheiro-header__professional-value">{{ $cro ?? 'CRO-MG 20.545' }}</span>
                    </td>
                </tr>
                <tr>
                    <td colspan="2" class="malheiro-header__specialties">
                        <span class="malheiro-header__specialty-main">{{ $especialidade_principal ?? 'ESPECIALISTA EM IMPLANTES' }}</span>
                        <span class="malheiro-header__specialty-secondary">{{ $especialidades[1] ?? 'DENTÍSTICA RESTAURADORA E ESTÉTICA' }}</span>
                    </td>
                </tr>
            </table>

            @if (! empty($frase_institucional))
                <div class="malheiro-header__tagline-institucional">“{{ $frase_institucional }}”</div>
            @endif
        </header>

        {{-- CONTEÚDO (seções das views filhas) --}}
        <main class="malheiro-content">
            @yield('content')
        </main>

        {{-- RODAPÉ FIXO (repetido em todas as páginas do PDF) --}}
        <footer class="malheiro-footer">
            <div class="malheiro-footer__clinic-name">{{ $clinica_nome ?? 'CLÍNICA MALHEIRO' }}</div>
            @if (! empty($rodape['endereco']))
                <div class="malheiro-footer__address">{{ $rodape['endereco'] }}</div>
            @endif
            <div class="malheiro-footer__contacts">
                @if (! empty($rodape['telefone']))
                    <span class="malheiro-footer__contact">{{ $rodape['telefone'] }}</span>
                @endif
                @if (! empty($rodape['whatsapp']))
                    <span class="malheiro-footer__contact">{{ $rodape['whatsapp'] }}</span>
                @endif
            </div>
        </footer>
    </div>
</body>
</html>

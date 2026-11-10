@extends('documents.layouts.malheiro')

@section('titulo', 'Atestado Odontológico — Clínica Malheiro')

@section('content')
    <h1 class="malheiro-content__title">ATESTADO ODONTOLÓGICO</h1>

    {{-- Tabela em vez de flex: é o que o Dompdf renderiza de forma confiável. --}}
    <table class="malheiro-atestado">
        <tr>
            <th class="malheiro-atestado__label">Paciente:</th>
            <td class="malheiro-atestado__value">{{ $paciente_nome ?? '' }}</td>
        </tr>
        <tr>
            <th class="malheiro-atestado__label">Identidade / CPF:</th>
            <td class="malheiro-atestado__value">{{ $paciente_cpf ?? '' }}</td>
        </tr>
        <tr>
            <th class="malheiro-atestado__label">Data do atendimento:</th>
            <td class="malheiro-atestado__value">{{ $atendimento_data ?? '' }}</td>
        </tr>
        <tr>
            <th class="malheiro-atestado__label">Horário:</th>
            <td class="malheiro-atestado__value">{{ $atendimento_hora ?? '' }}</td>
        </tr>
        <tr>
            <th class="malheiro-atestado__label">Dias de repouso:</th>
            <td class="malheiro-atestado__value">{{ $dias_repouso ?? '' }}</td>
        </tr>
        <tr>
            <th class="malheiro-atestado__label">Local:</th>
            <td class="malheiro-atestado__value">{{ $local ?? $rodape['cidade'] ?? '' }}/{{ $rodape['estado'] ?? '' }}</td>
        </tr>
        <tr>
            <th class="malheiro-atestado__label">Data de emissão:</th>
            <td class="malheiro-atestado__value">{{ $data_emissao ?? date('d/m/Y') }}</td>
        </tr>
    </table>

    <div class="malheiro-atestado__signature">
        <div class="malheiro-atestado__signature-line"></div>
        <div class="malheiro-atestado__signature-name">{{ $nome_profissional ?? 'Dr. Alessandro Agostinho Malheiro' }}</div>
        <div class="malheiro-atestado__signature-cro">{{ $cro ?? 'CRO-MG 20.545' }}</div>
        <div class="malheiro-atestado__signature-cro malheiro-atestado__signature-cro--sub">
            {{ $especialidade_principal ?? 'ESPECIALISTA EM IMPLANTES' }} | {{ $especialidades[1] ?? 'DENTÍSTICA RESTAURADORA E ESTÉTICA' }}
        </div>
    </div>

    <div class="malheiro-atestado__legal">
        <strong>Observação legal:</strong> Este atestado é emitido para fins de comprovação de atendimento odontológico e afastamento das atividades laborais, conforme legislação vigente. A falsificação ou alteração deste documento constitui crime. Em caso de dúvidas, entre em contato com a clínica pelos telefones disponíveis no rodapé.
    </div>
@endsection

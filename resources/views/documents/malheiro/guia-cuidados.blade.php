@extends('documents.layouts.malheiro')

@section('titulo', 'Guia de Cuidados Odontológicos — Clínica Malheiro')

@section('content')
    <h1 class="malheiro-content__title">
        A VOCÊ, CARO PACIENTE, QUE ESTÁ INICIANDO<br>
        <span class="malheiro-content__title-sub">ESTE TRATAMENTO ODONTOLÓGICO.</span>
    </h1>

    {{-- Números explícitos no HTML: o Dompdf não suporta contadores CSS. --}}
    <table class="malheiro-list">
        <tr>
            <td class="malheiro-list__number">1</td>
            <td class="malheiro-list__text"><strong>Hábitos de escovação:</strong> seu cônjuge e/ou usar o fio dental após as refeições e antes de dormir, seguindo a técnica de escovação indicada.{{-- NOTA: O texto original da referência contém "seu cônjuge e/ou usar", aparente erro de digitação. Mantido EXATAMENTE como no original, aguardando decisão do Malheiro. --}}</td>
        </tr>
        <tr>
            <td class="malheiro-list__number">2</td>
            <td class="malheiro-list__text"><strong>Venha ao consultório regularmente,</strong> para a manutenção do seu tratamento e para que possamos acompanhar a evolução da sua saúde bucal, lembrando que prevenir sempre será melhor que remediar.</td>
        </tr>
        <tr>
            <td class="malheiro-list__number">3</td>
            <td class="malheiro-list__text"><strong>Escove os dentes toda vez que vier ao dentista,</strong> complementando a higienização com o uso do fio dental.</td>
        </tr>
        <tr>
            <td class="malheiro-list__number">4</td>
            <td class="malheiro-list__text"><strong>Se lhe for receitado algum medicamento,</strong> tome-o nos horários e quantidades indicadas.</td>
        </tr>
        <tr>
            <td class="malheiro-list__number">5</td>
            <td class="malheiro-list__text"><strong>As faltas ou atrasos às consultas,</strong> além de prejudicarem e prolongarem seu tratamento odontológico, podem acarretar na cobrança do valor das consultas.</td>
        </tr>
        <tr>
            <td class="malheiro-list__number">6</td>
            <td class="malheiro-list__text"><strong>O horário marcado é para controle do consultório</strong> e está sujeito a atrasos internos, em benefício do próprio paciente que está sendo atendido.</td>
        </tr>
        <tr>
            <td class="malheiro-list__number">7</td>
            <td class="malheiro-list__text"><strong>O sucesso de seu tratamento não depende só do dentista.</strong> Se não houver colaboração de sua parte no sentido de higiene bucal e cooperação no cumprimento dos retornos, o resultado pode não ser o esperado, e complicações poderão ocorrer sem nenhuma responsabilidade do profissional.</td>
        </tr>
    </table>

    <div class="malheiro-divider--teal"></div>

    <div class="malheiro-highlight">
        <p class="malheiro-highlight__main">Contamos com a sua colaboração para o sucesso do seu tratamento.</p>
        <p class="malheiro-highlight__sub">
            {{ $nome_profissional ?? 'Dr. Alessandro Agostinho Malheiro' }}<br>
            <span class="malheiro-text-teal">{{ $especialidade_principal ?? 'ESPECIALISTA EM IMPLANTES' }}</span>
        </p>
    </div>
@endsection

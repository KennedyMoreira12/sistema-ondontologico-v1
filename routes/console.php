<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
 * Provedores assíncronos (Focus NFe, PlugNotas) respondem `processando` e só
 * confirmam depois. Sem esta varredura a nota ficaria pendente na tela para
 * sempre. `withoutOverlapping` evita dois syncs ao mesmo tempo quando um lote
 * demora mais que o intervalo.
 */
Schedule::command('fiscal:sincronizar')
    ->everyThirtyMinutes()
    ->withoutOverlapping();

/**
 * Recuperação da outbox do WhatsApp.
 *
 * `withoutOverlapping` usa o cache como trava: sem ele, duas execuções
 * simultâneas reenfileirariam a mesma linha. Não é `onOneServer` porque a
 * instalação pode usar cache de arquivo, que não é atômico entre servidores.
 *
 * O agendador precisa do cron do Laravel (`schedule:run`) rodando. Sem ele, o
 * comando não executa sozinho — o runbook cobre isso.
 */
Schedule::command('whatsapp:outbox:recover')
    ->everyMinute()
    ->withoutOverlapping();

/**
 * Lembretes de consulta com 24h de antecedência.
 *
 * O comando só enfileira; quem entrega é o `whatsapp:outbox:recover` acima.
 * Roda no começo da tarde para que o paciente receba o lembrete em horário
 * comercial e ainda tenha tempo de responder "confirmar" no mesmo dia.
 */
Schedule::command('whatsapp:lembretes')
    ->dailyAt('14:00')
    ->withoutOverlapping();

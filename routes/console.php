<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

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

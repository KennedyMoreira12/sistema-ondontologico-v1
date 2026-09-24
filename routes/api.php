<?php

use App\Http\Controllers\Api\WhatsAppController;
use App\Http\Middleware\WhatsAppApiAuth;
use Illuminate\Support\Facades\Route;

Route::prefix('whatsapp')
    ->name('api.whatsapp.')
    ->middleware(WhatsAppApiAuth::class)
    ->group(function () {
        Route::get('info', [WhatsAppController::class, 'info'])->name('info');
        Route::get('profissionais', [WhatsAppController::class, 'profissionais'])->name('profissionais');

        Route::get('paciente', [WhatsAppController::class, 'paciente'])->name('paciente');
        Route::post('paciente', [WhatsAppController::class, 'criarPaciente'])->name('criar-paciente');

        Route::get('disponibilidade', [WhatsAppController::class, 'disponibilidade'])->name('disponibilidade');
        Route::post('agendamento', [WhatsAppController::class, 'agendamento'])->name('agendamento');

        Route::get('agendamentos', [WhatsAppController::class, 'agendamentosPaciente'])->name('agendamentos');
        Route::post('agendamento/{consulta}/confirmar', [WhatsAppController::class, 'confirmarAgendamento'])->name('confirmar');
        Route::post('agendamento/{consulta}/cancelar', [WhatsAppController::class, 'cancelarAgendamento'])->name('cancelar');
    });

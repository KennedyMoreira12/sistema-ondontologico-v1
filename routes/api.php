<?php

use App\Http\Controllers\Api\MetaWebhookController;
use App\Http\Controllers\Api\WhatsAppController;
use App\Http\Middleware\AuthenticateIntegration;
use App\Http\Middleware\EnsureIntegrationAbility;
use Illuminate\Support\Facades\Route;

/**
 * Webhook da Meta: fica fora do grupo autenticado porque a Meta não tem
 * token de clínica. A confiança vem do `hub.verify_token` (GET) e do HMAC
 * `X-Hub-Signature-256` (POST), ambos checados no controller.
 */
Route::prefix('meta/webhook')
    ->name('api.meta.webhook.')
    ->controller(MetaWebhookController::class)
    ->group(function () {
        Route::get('whatsapp', 'verify')->name('verify');
        Route::post('whatsapp', 'receive')->name('receive');
    });

Route::prefix('whatsapp')
    ->name('api.whatsapp.')
    ->middleware([AuthenticateIntegration::class, EnsureIntegrationAbility::class])
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

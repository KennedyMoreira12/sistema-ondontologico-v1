<?php

use App\Http\Controllers\Api\EvolutionWebhookController;
use App\Http\Controllers\Api\MetaWebhookController;
use App\Http\Controllers\Api\WhatsAppController;
use App\Http\Middleware\AuthenticateEvolutionWebhook;
use App\Http\Middleware\AuthenticateIntegration;
use App\Http\Middleware\EnsureIntegrationAbility;
use Illuminate\Support\Facades\Route;

/**
 * Webhook da Evolution API.
 *
 * Rota pública, e não tem id de clínica na URL: a Evolution manda o nome da
 * instância no corpo e é por ele que a clínica é resolvida. O path com `{clinic}`
 * deixaria quem conhece o nome da instância — que é dado público no payload do
 * próprio webhook — escrever no banco de outra clínica.
 *
 * A Evolution não assina o webhook, então a confiança vem das duas checagens do
 * `AuthenticateEvolutionWebhook`: o segredo no header e o token da instância no
 * corpo. Sem `EVOLUTION_WEBHOOK_SECRET` a rota recusa todo POST.
 *
 * `throttle:evolution` limita por instância, e não por IP: todas as instâncias
 * saem do mesmo endereço da VPS, e um limite por IP faria a clínica mais barulhenta
 * estourar o limite das outras.
 */
Route::prefix('evolution/webhook')
    ->name('api.evolution.webhook.')
    ->controller(EvolutionWebhookController::class)
    ->middleware([AuthenticateEvolutionWebhook::class, 'throttle:evolution'])
    ->group(function () {
        Route::post('/', 'handle')->name('handle');
    });

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

/**
 * API interna do WhatsApp, consumida pelo n8n.
 *
 * `plan:whatsapp` entra depois da autenticação porque é o token que resolve a
 * clínica, e é a clínica que diz qual assinatura — e qual plano — vale. Sem ele,
 * o recurso que só o Premium vende ficaria liberado para Starter e Pro.
 *
 * `throttle:integration` lê o header do token direto, então a ordem em relação
 * aos dois middlewares de autenticação não muda o resultado do limite.
 */
Route::prefix('whatsapp')
    ->name('api.whatsapp.')
    ->middleware([
        AuthenticateIntegration::class,
        EnsureIntegrationAbility::class,
        'plan:whatsapp',
        'throttle:integration',
    ])
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

        /**
         * A resposta do bot. Fica por último porque é a única rota que ESCREVE
         * fora do banco da clínica: ela fala com o WhatsApp do paciente. A
         * ability `messages.send` é separada de propósito — quem pode ler a
         * agenda não pode, sozinho, mandar mensagem em nome da clínica.
         */
        Route::post('mensagem', [WhatsAppController::class, 'enviarMensagem'])->name('mensagem');
    });

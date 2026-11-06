<?php

use App\Http\Controllers\Api\EvolutionWebhookController;
use App\Http\Controllers\Api\MetaWebhookController;
use App\Http\Controllers\Api\SocialPostController;
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

        /**
         * Diagnóstico da conexão e da outbox. Sem ability própria: não expõe
         * dado de paciente e é o que o n8n usa para distinguir "token errado"
         * de "WhatsApp desconectado". A chamada externa à Evolution só acontece
         * com `check_evolution=1`.
         */
        Route::get('saude', [WhatsAppController::class, 'saude'])->name('saude');
        Route::get('profissionais', [WhatsAppController::class, 'profissionais'])->name('profissionais');
        Route::get('procedimentos', [WhatsAppController::class, 'procedimentos'])->name('procedimentos');

        Route::get('paciente', [WhatsAppController::class, 'paciente'])->name('paciente');
        Route::post('paciente', [WhatsAppController::class, 'criarPaciente'])->name('criar-paciente');

        /**
         * Um turno da conversa. É a rota que o n8n chama por mensagem: a máquina
         * de estados mora no Laravel porque o Code node do n8n não tem HTTP nesta
         * instalação. Ela concentra o que o bot precisaria para conduzir a
         * conversa inteira, então exige uma ability própria em vez de herdar
         * `appointments.create` — ler a agenda e agendar é diferente de avançar a
         * conversa de um paciente até o fim.
         */
        Route::post('fluxo', [WhatsAppController::class, 'fluxo'])->name('fluxo');

        /**
         * Turno COM IA. Ability própria (`conversations.ai`) e uma camada de
         * plano a mais (`plan:ai_agent`): ter WhatsApp não é ter IA, e o token
         * que conduz a conversa determinística não ganha, sozinho, o direito de
         * gastar a cota de IA da clínica.
         */
        Route::post('ia', [WhatsAppController::class, 'fluxoIA'])
            ->name('ia')
            ->middleware('plan:ai_agent');

        /**
         * Pausa e retomada do bot para atendimento humano. Não exigem
         * `ai_agent`: quem atende no balcão precisa poder assumir a conversa
         * mesmo que o plano não tenha IA.
         */
        Route::post('conversa/handoff', [WhatsAppController::class, 'handoff'])->name('handoff');
        Route::post('conversa/retomar', [WhatsAppController::class, 'retomar'])->name('retomar');

        /**
         * Operações de atendimento humano: fila de espera, respostas rápidas e
         * notas internas. Todas exigem `conversations.handoff` — é a mesma
         * pessoa (recepção) que assume a conversa e usa o material de apoio.
         */
        Route::get('alertas', [WhatsAppController::class, 'alertas'])->name('alertas');
        Route::get('respostas-rapidas', [WhatsAppController::class, 'respostasRapidas'])->name('respostas-rapidas');
        Route::get('notas', [WhatsAppController::class, 'notas'])->name('notas');
        Route::post('notas', [WhatsAppController::class, 'criarNota'])->name('notas.criar');

        Route::get('disponibilidade', [WhatsAppController::class, 'disponibilidade'])->name('disponibilidade');
        Route::get('conversa', [WhatsAppController::class, 'conversa'])->name('conversa');
        Route::put('conversa', [WhatsAppController::class, 'conversaAtualizar'])->name('conversa.atualizar');
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

        /**
         * Menu de botões/lista. Mesma ability de `mensagem`: é o mesmo efeito
         * externo, só muda o formato. A rota devolve `fallback_texto` e cai
         * sozinha para ele quando a Evolution recusa o formato interativo.
         */
        Route::post('mensagem-interativa', [WhatsAppController::class, 'enviarMensagemInterativa'])
            ->name('mensagem-interativa');
    });

/**
 * API isolada do módulo de redes sociais.
 *
 * Separa os posts sociais do atendimento do WhatsApp: o token que gerencia
 * uma agenda de clínica não precisa responder também pela agenda editorial.
 */
Route::prefix('social')
    ->name('api.social.')
    ->middleware([
        AuthenticateIntegration::class,
        EnsureIntegrationAbility::class,
        'throttle:integration',
    ])
    ->group(function () {
        Route::get('posts', [SocialPostController::class, 'index'])->name('posts.index');
        Route::post('posts', [SocialPostController::class, 'store'])->name('posts.store');
        Route::post('posts/{id}/image', [SocialPostController::class, 'storeImage'])->name('posts.image');
        Route::get('posts/{id}', [SocialPostController::class, 'show'])->name('posts.show');
        Route::patch('posts/{id}/approve', [SocialPostController::class, 'approve'])->name('posts.approve');
        Route::patch('posts/{id}/reject', [SocialPostController::class, 'reject'])->name('posts.reject');
    });

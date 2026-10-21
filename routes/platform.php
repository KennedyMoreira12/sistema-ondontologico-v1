<?php

use App\Http\Controllers\Platform\AuditLogController;
use App\Http\Controllers\Platform\Auth\LoginController;
use App\Http\Controllers\Platform\ClinicController;
use App\Http\Controllers\Platform\DashboardController;
use App\Http\Controllers\Platform\PlanController;
use App\Http\Controllers\Platform\SocialPostController;
use App\Http\Controllers\Platform\SocialSettingController;
use App\Http\Controllers\Platform\SubscriptionController;
use Illuminate\Support\Facades\Route;

/**
 * Painel da empresa (a sua equipe): clínicas, planos, assinaturas e auditoria.
 *
 * Fica em arquivo separado e NAO carrega o middleware `tenant`: quem opera a
 * plataforma não pertence a nenhuma clínica, e conectar no banco de um paciente
 * aqui seria exatamente o vazamento que o ResolveTenant existe para impedir.
 */
Route::prefix('painel')->name('painel.')->group(function (): void {
    /**
     * O painel não tem mais tela de login própria: a entrada é a mesma tela de
     * `/login`, cujo formulário tenta o guard `admin` antes do `web`. Esta rota
     * sobra só para links antigos e para o `redirectGuestsTo` do bootstrap, e
     * por isso redireciona em vez de 404.
     */
    Route::middleware('guest:admin')->group(function (): void {
        Route::redirect('login', '/login')->name('login');
    });

    Route::middleware(['auth:admin', 'platform.active'])->group(function (): void {
        Route::post('logout', [LoginController::class, 'destroy'])->name('logout');

        Route::get('/', DashboardController::class)->name('dashboard');

        Route::prefix('clinicas')->name('clinicas.')->group(function (): void {
            Route::get('/', [ClinicController::class, 'index'])->name('index');
            Route::get('create', [ClinicController::class, 'create'])->name('create');
            Route::post('/', [ClinicController::class, 'store'])->name('store');
            Route::get('{clinic}', [ClinicController::class, 'show'])->name('show');
            Route::get('{clinic}/edit', [ClinicController::class, 'edit'])->name('edit');
            Route::put('{clinic}', [ClinicController::class, 'update'])->name('update');
            Route::delete('{clinic}', [ClinicController::class, 'destroy'])->name('destroy');

            Route::post('{clinic}/migrar', [ClinicController::class, 'migrate'])->name('migrate');
            Route::patch('{clinic}/conexao', [ClinicController::class, 'toggleConnection'])->name('conexao');
            Route::post('{clinic}/tokens', [ClinicController::class, 'storeToken'])->name('tokens.store');
        });

        Route::prefix('planos')->name('planos.')->group(function (): void {
            Route::get('/', [PlanController::class, 'index'])->name('index');
            Route::get('create', [PlanController::class, 'create'])->name('create');
            Route::post('/', [PlanController::class, 'store'])->name('store');
            Route::get('{plan}/edit', [PlanController::class, 'edit'])->name('edit');
            Route::put('{plan}', [PlanController::class, 'update'])->name('update');
            Route::delete('{plan}', [PlanController::class, 'destroy'])->name('destroy');
        });

        Route::prefix('assinaturas')->name('assinaturas.')->group(function (): void {
            Route::get('/', [SubscriptionController::class, 'index'])->name('index');
            Route::post('{clinic}', [SubscriptionController::class, 'store'])->name('store');
            Route::post('{subscription}/renovar', [SubscriptionController::class, 'renew'])->name('renovar');
            Route::patch('{subscription}/status', [SubscriptionController::class, 'status'])->name('status');
        });

        Route::get('auditoria', [AuditLogController::class, 'index'])->name('auditoria.index');

        /**
         * Prospecção comercial. Fica no banco central e é exclusiva do super
         * admin (superadmin), diferente do resto do painel, que o suporte
         * também abrange. Não envia mensagem nem toca no Evolution.
         */
        Route::prefix('prospeccao')->name('prospeccao.')->middleware('superadmin')->group(function (): void {
            Route::get('/', [ProspectController::class, 'index'])->name('index');
            Route::get('origem/{source}', [ProspectController::class, 'origem'])->name('origem');

            /**
             * Importação por planilha em duas etapas (prévia -> confirmação).
             * Fica ANTES de {prospect} para que "importar" não seja capturado
             * como id de contato.
             */
            Route::get('importar', [ProspectImportController::class, 'create'])->name('importar');
            Route::post('importar', [ProspectImportController::class, 'store'])->name('importar.store');
            Route::get('importar/previa', [ProspectImportController::class, 'preview'])->name('importar.previa');
            Route::post('importar/confirmar', [ProspectImportController::class, 'confirm'])->name('importar.confirmar');
            Route::post('importar/cancelar', [ProspectImportController::class, 'cancel'])->name('importar.cancelar');

            /**
             * WhatsApp exclusivo da prospecção. Fica ANTES de {prospect} para
             * que whatsapp não seja capturado como id de contato. O QR tem
             * throttle próprio; criar instância e ler estado também são
             * limitados para não virar bomba na Evolution.
             */
            Route::prefix('whatsapp')->name('whatsapp.')->group(function (): void {
                Route::get('/', [ProspectWhatsAppController::class, 'show'])->name('show');
                Route::post('instancia', [ProspectWhatsAppController::class, 'storeInstance'])
                    ->middleware('throttle:10,1')
                    ->name('instancia');
                Route::post('estado', [ProspectWhatsAppController::class, 'refreshState'])
                    ->middleware('throttle:10,1')
                    ->name('estado');
                Route::post('qrcode', [ProspectWhatsAppController::class, 'qrcode'])
                    ->middleware('throttle:10,1')
                    ->name('qrcode');
            });

            Route::post('comercial', [ProspectController::class, 'comercial'])->name('comercial');

            Route::get('{prospect}', [ProspectController::class, 'show'])->name('show');

            Route::patch('{prospect}', [ProspectController::class, 'update'])->name('update');
        });

        /**
         * Redes sociais DA PLATAFORMA (a LJ). Banco central, exclusiva do super
         * admin. A publicacao e manual ("Publicar agora") e usa as contas
         * configuradas em platform_social_settings; nada aqui e de clinica.
         * configuracao e create ficam antes de {post} para nao serem
         * capturados como id.
         */
        Route::prefix('redes-sociais')->name('social.')->middleware('superadmin')->group(function (): void {
            Route::get('/', [SocialPostController::class, 'index'])->name('index');
            Route::get('create', [SocialPostController::class, 'create'])->name('create');
            Route::post('/', [SocialPostController::class, 'store'])->name('store');

            Route::get('configuracao', [SocialSettingController::class, 'edit'])->name('configuracao');
            Route::put('configuracao', [SocialSettingController::class, 'update'])->name('configuracao.update');
            Route::post('configuracao/verificar', [SocialSettingController::class, 'verificar'])->name('configuracao.verificar');

            Route::get('{post}', [SocialPostController::class, 'show'])->name('show');
            Route::post('{post}/publicar', [SocialPostController::class, 'publish'])->name('publicar');
            Route::delete('{post}', [SocialPostController::class, 'destroy'])->name('destroy');
        });
    });
});

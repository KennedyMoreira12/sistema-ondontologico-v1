<?php

use App\Http\Controllers\Platform\AuditLogController;
use App\Http\Controllers\Platform\Auth\LoginController;
use App\Http\Controllers\Platform\ClinicController;
use App\Http\Controllers\Platform\DashboardController;
use App\Http\Controllers\Platform\PlanController;
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
    });
});

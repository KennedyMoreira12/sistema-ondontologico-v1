<?php

use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\ClinicaController;
use App\Http\Controllers\Admin\PermissionController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\AgendaController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\OdontogramaController;
use App\Http\Controllers\PacienteController;
use App\Http\Controllers\PrescricaoController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TratamentoPlanoController;
use Illuminate\Support\Facades\Route;

Route::get('/', DashboardController::class)
    ->middleware(['auth'])
    ->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::prefix('agenda')->name('agenda.')->group(function () {
        Route::get('/', [AgendaController::class, 'index'])->name('index');
        Route::post('/', [AgendaController::class, 'store'])->name('store');
        Route::put('/{consulta}', [AgendaController::class, 'update'])->name('update');
        Route::delete('/{consulta}', [AgendaController::class, 'destroy'])->name('destroy');
        Route::post('/lista-espera', [AgendaController::class, 'storeWaiting'])->name('waiting.store');
        Route::patch('/lista-espera/{waiting}', [AgendaController::class, 'updateWaiting'])->name('waiting.update');
        Route::delete('/lista-espera/{waiting}', [AgendaController::class, 'destroyWaiting'])->name('waiting.destroy');
    });

    Route::prefix('pacientes')->name('pacientes.')->group(function () {
        Route::get('/', [PacienteController::class, 'index'])->name('index');
        Route::get('/create', [PacienteController::class, 'create'])->name('create');
        Route::post('/', [PacienteController::class, 'store'])->name('store');
        Route::get('/{paciente}', [PacienteController::class, 'show'])->name('show');
        Route::get('/{paciente}/edit', [PacienteController::class, 'edit'])->name('edit');
        Route::put('/{paciente}', [PacienteController::class, 'update'])->name('update');
        Route::delete('/{paciente}', [PacienteController::class, 'destroy'])->name('destroy');
        Route::get('/{paciente}/prontuario', [PacienteController::class, 'prontuario'])->name('prontuario');
        Route::post('/{paciente}/evolucoes', [PacienteController::class, 'storeEvolucao'])->name('evolucoes.store');
        Route::post('/{paciente}/anamneses', [PacienteController::class, 'storeAnamnese'])->name('anamneses.store');
        Route::post('/{paciente}/alertas', [PacienteController::class, 'storeAlerta'])->name('alertas.store');
        Route::delete('/{paciente}/alertas/{alerta}', [PacienteController::class, 'destroyAlerta'])->name('alertas.destroy');
        Route::post('/{paciente}/documentos', [PacienteController::class, 'storeDocumento'])->name('documentos.store');
        Route::get('/{paciente}/documentos/{documento}/download', [PacienteController::class, 'downloadDocumento'])->name('documentos.download');
        Route::delete('/{paciente}/documentos/{documento}', [PacienteController::class, 'destroyDocumento'])->name('documentos.destroy');

        Route::get('/{paciente}/odontograma', [OdontogramaController::class, 'index'])->name('odontograma.index');
        Route::put('/{paciente}/odontograma/dente/{dente}', [OdontogramaController::class, 'updateTooth'])->name('odontograma.tooth.update');
        Route::delete('/{paciente}/odontograma/dente/{dente}', [OdontogramaController::class, 'destroyTooth'])->name('odontograma.tooth.destroy');

        Route::get('/{paciente}/prescricoes', [PrescricaoController::class, 'index'])->name('prescricoes.index');
        Route::post('/{paciente}/prescricoes', [PrescricaoController::class, 'store'])->name('prescricoes.store');
        Route::delete('/{paciente}/prescricoes/{prescricao}', [PrescricaoController::class, 'destroy'])->name('prescricoes.destroy');

        Route::get('/{paciente}/planos', [TratamentoPlanoController::class, 'index'])->name('planos.index');
        Route::post('/{paciente}/planos', [TratamentoPlanoController::class, 'store'])->name('planos.store');
        Route::get('/{paciente}/planos/{plano}', [TratamentoPlanoController::class, 'show'])->name('planos.show');
        Route::put('/{paciente}/planos/{plano}', [TratamentoPlanoController::class, 'update'])->name('planos.update');
        Route::patch('/{paciente}/planos/{plano}/status', [TratamentoPlanoController::class, 'status'])->name('planos.status');
        Route::delete('/{paciente}/planos/{plano}', [TratamentoPlanoController::class, 'destroy'])->name('planos.destroy');
        Route::post('/{paciente}/planos/{plano}/itens', [TratamentoPlanoController::class, 'itemStore'])->name('planos.items.store');
        Route::put('/{paciente}/planos/{plano}/itens/{item}', [TratamentoPlanoController::class, 'itemUpdate'])->name('planos.items.update');
        Route::patch('/{paciente}/planos/{plano}/itens/{item}/status', [TratamentoPlanoController::class, 'itemStatus'])->name('planos.items.status');
        Route::delete('/{paciente}/planos/{plano}/itens/{item}', [TratamentoPlanoController::class, 'itemDestroy'])->name('planos.items.destroy');
    });

    Route::prefix('admin')->name('admin.')->middleware('can:admin.view')->group(function () {
        Route::resource('usuarios', UserController::class)->except(['show']);
        Route::get('perfis', [RoleController::class, 'index'])->name('perfis.index');
        Route::get('perfis/{role}/edit', [RoleController::class, 'edit'])->name('perfis.edit');
        Route::put('perfis/{role}', [RoleController::class, 'update'])->name('perfis.update');
        Route::get('permissoes', [PermissionController::class, 'index'])->name('permissoes.index');
        Route::get('clinica', [ClinicaController::class, 'edit'])->name('clinica.edit');
        Route::put('clinica/{clinica}', [ClinicaController::class, 'update'])->name('clinica.update');
        Route::get('auditoria', [AuditLogController::class, 'index'])->name('auditoria.index');
    });
});

require __DIR__.'/auth.php';

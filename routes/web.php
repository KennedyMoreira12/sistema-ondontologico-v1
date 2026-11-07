<?php

use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\ClinicaController;
use App\Http\Controllers\Admin\FiscalController;
use App\Http\Controllers\Admin\PermissionController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\AgendaController;
use App\Http\Controllers\Api\EvolutionQrController;
use App\Http\Controllers\AtendimentoController;
use App\Http\Controllers\AvaliacaoController;
use App\Http\Controllers\ContaPagarController;
use App\Http\Controllers\ContratoController;
use App\Http\Controllers\ConvenioController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EstoqueController;
use App\Http\Controllers\EvolucaoSessaoController;
use App\Http\Controllers\FinanceiroController;
use App\Http\Controllers\FornecedorController;
use App\Http\Controllers\ModuloPlanejadoController;
use App\Http\Controllers\OdontogramaController;
use App\Http\Controllers\OrcamentoController;
use App\Http\Controllers\PacienteController;
use App\Http\Controllers\PacienteDuplicataController;
use App\Http\Controllers\PacienteImportacaoController;
use App\Http\Controllers\PacoteSessaoController;
use App\Http\Controllers\PagamentoController;
use App\Http\Controllers\PrescricaoController;
use App\Http\Controllers\ProcedimentoController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProfissionalController;
use App\Http\Controllers\RelatorioController;
use App\Http\Controllers\SocialPostController;
use App\Http\Controllers\SocialSettingController;
use App\Http\Controllers\TratamentoPlanoController;
use Illuminate\Support\Facades\Route;

Route::get('/', DashboardController::class)
    ->middleware(['auth', 'tenant'])
    ->name('dashboard');

Route::middleware(['auth', 'tenant'])->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Tela padrão de módulo planejado. Um módulo que já está disponível
    // redireciona para a própria tela; um módulo de outra vertical é recusado
    // aqui e no servidor.
    Route::get('/modulos/{modulo}', [ModuloPlanejadoController::class, '__invoke'])->name('modulos.planejado');

    Route::prefix('agenda')->name('agenda.')->group(function () {
        Route::get('/', [AgendaController::class, 'index'])->name('index');
        Route::get('/dia', [AgendaController::class, 'dia'])->name('dia');
        Route::get('/mes', [AgendaController::class, 'mes'])->name('mes');
        Route::get('/disponiveis', [AgendaController::class, 'disponiveis'])->name('disponiveis');
        Route::post('/', [AgendaController::class, 'store'])->name('store');
        Route::put('/{consulta}', [AgendaController::class, 'update'])->name('update');
        Route::patch('/{consulta}/reagendar', [AgendaController::class, 'reagendar'])->name('reagendar');
        Route::post('/{consulta}/presenca', [AgendaController::class, 'presenca'])->name('presenca');
        Route::delete('/{consulta}', [AgendaController::class, 'destroy'])->name('destroy');
        Route::post('/lista-espera', [AgendaController::class, 'storeWaiting'])->name('waiting.store');
        Route::patch('/lista-espera/{waiting}', [AgendaController::class, 'updateWaiting'])->name('waiting.update');
        Route::delete('/lista-espera/{waiting}', [AgendaController::class, 'destroyWaiting'])->name('waiting.destroy');
    });

    Route::prefix('atendimentos')->name('atendimentos.')->group(function () {
        Route::get('/', [AtendimentoController::class, 'index'])->name('index');
        Route::post('/{consulta}/consumir', [AtendimentoController::class, 'consumir'])->name('consumir');
    });

    Route::prefix('financeiro')->name('financeiro.')->group(function () {
        Route::get('/', [FinanceiroController::class, 'index'])->name('index');
        Route::post('/{conta}/pagamentos', [FinanceiroController::class, 'receive'])->name('receive');
        Route::get('/fluxo-caixa', [FinanceiroController::class, 'fluxoCaixa'])->name('fluxo-caixa');

        Route::get('/pagamentos', [PagamentoController::class, 'index'])->name('pagamentos.index');
        Route::post('/pagamentos', [PagamentoController::class, 'store'])->name('pagamentos.store');
        Route::get('/pagamentos/{pagamento}/revisar', [PagamentoController::class, 'revisar'])->name('pagamentos.revisar');
        Route::post('/pagamentos/{pagamento}/vincular', [PagamentoController::class, 'vincular'])->name('pagamentos.vincular');
        Route::delete('/pagamentos/{pagamento}/vincular', [PagamentoController::class, 'desvincular'])->name('pagamentos.desvincular');

        Route::get('/duplicatas', [PacienteDuplicataController::class, 'index'])->name('duplicatas.index');

        Route::get('/contas-pagar', [ContaPagarController::class, 'index'])->name('contas-pagar.index');
        Route::get('/contas-pagar/create', [ContaPagarController::class, 'create'])->name('contas-pagar.create');
        Route::post('/contas-pagar', [ContaPagarController::class, 'store'])->name('contas-pagar.store');
        Route::get('/contas-pagar/{conta}', [ContaPagarController::class, 'show'])->name('contas-pagar.show');
        Route::get('/contas-pagar/{conta}/edit', [ContaPagarController::class, 'edit'])->name('contas-pagar.edit');
        Route::put('/contas-pagar/{conta}', [ContaPagarController::class, 'update'])->name('contas-pagar.update');
        Route::patch('/contas-pagar/{conta}/cancelar', [ContaPagarController::class, 'cancelar'])->name('contas-pagar.cancelar');
        Route::post('/contas-pagar/{conta}/baixas', [ContaPagarController::class, 'baixar'])->name('contas-pagar.baixar');

        Route::get('/fornecedores', [FornecedorController::class, 'index'])->name('fornecedores.index');
        Route::get('/fornecedores/create', [FornecedorController::class, 'create'])->name('fornecedores.create');
        Route::post('/fornecedores', [FornecedorController::class, 'store'])->name('fornecedores.store');
        Route::get('/fornecedores/{fornecedor}/edit', [FornecedorController::class, 'edit'])->name('fornecedores.edit');
        Route::put('/fornecedores/{fornecedor}', [FornecedorController::class, 'update'])->name('fornecedores.update');
        Route::delete('/fornecedores/{fornecedor}', [FornecedorController::class, 'destroy'])->name('fornecedores.destroy');
    });

    Route::prefix('convenios')->name('convenios.')->group(function () {
        Route::get('/', [ConvenioController::class, 'index'])->name('index');
        Route::get('/create', [ConvenioController::class, 'create'])->name('create');
        Route::post('/', [ConvenioController::class, 'store'])->name('store');
        Route::get('/{operadora}/edit', [ConvenioController::class, 'edit'])->name('edit');
        Route::put('/{operadora}', [ConvenioController::class, 'update'])->name('update');
        Route::delete('/{operadora}', [ConvenioController::class, 'destroy'])->name('destroy');
    });

    Route::prefix('profissionais')->name('profissionais.')->group(function () {
        Route::get('/', [ProfissionalController::class, 'index'])->name('index');
        Route::get('/create', [ProfissionalController::class, 'create'])->name('create');
        Route::post('/', [ProfissionalController::class, 'store'])->name('store');
        Route::get('/{profissional}/edit', [ProfissionalController::class, 'edit'])->name('edit');
        Route::put('/{profissional}', [ProfissionalController::class, 'update'])->name('update');
        Route::delete('/{profissional}', [ProfissionalController::class, 'destroy'])->name('destroy');
    });

    Route::prefix('procedimentos')->name('procedimentos.')->group(function () {
        Route::get('/', [ProcedimentoController::class, 'index'])->name('index');
        Route::get('/create', [ProcedimentoController::class, 'create'])->name('create');
        Route::post('/', [ProcedimentoController::class, 'store'])->name('store');
        Route::get('/{procedimento}/edit', [ProcedimentoController::class, 'edit'])->name('edit');
        Route::put('/{procedimento}', [ProcedimentoController::class, 'update'])->name('update');
        Route::delete('/{procedimento}', [ProcedimentoController::class, 'destroy'])->name('destroy');
    });

    Route::prefix('contratos')->name('contratos.')->group(function () {
        Route::get('/', [ContratoController::class, 'index'])->name('index');
        Route::get('/create', [ContratoController::class, 'create'])->name('create');
        Route::post('/', [ContratoController::class, 'store'])->name('store');
        Route::get('/{contrato}/edit', [ContratoController::class, 'edit'])->name('edit');
        Route::put('/{contrato}', [ContratoController::class, 'update'])->name('update');
        Route::delete('/{contrato}', [ContratoController::class, 'destroy'])->name('destroy');
    });

    Route::prefix('social')->name('social.')->group(function () {
        Route::get('/', [SocialPostController::class, 'index'])->name('index');
        Route::get('/create', [SocialPostController::class, 'create'])->name('create');
        Route::post('/', [SocialPostController::class, 'store'])->name('store');

        Route::get('/configuracao', [SocialSettingController::class, 'edit'])->name('configuracao');
        Route::put('/configuracao', [SocialSettingController::class, 'update'])->name('configuracao.update');
        Route::post('/configuracao/verificar', [SocialSettingController::class, 'verificar'])->name('configuracao.verificar');

        Route::get('/{socialPost}', [SocialPostController::class, 'show'])->name('show');
        Route::post('/{socialPost}/image', [SocialPostController::class, 'uploadImage'])->name('image');
        Route::post('/{socialPost}/publicar', [SocialPostController::class, 'publish'])->name('publicar');
        Route::patch('/{socialPost}/approve', [SocialPostController::class, 'approve'])->name('approve');
        Route::patch('/{socialPost}/reject', [SocialPostController::class, 'reject'])->name('reject');
    });

    Route::prefix('estoque')->name('estoque.')->group(function () {
        Route::get('/', [EstoqueController::class, 'index'])->name('index');
        Route::get('/create', [EstoqueController::class, 'create'])->name('create');
        Route::post('/', [EstoqueController::class, 'store'])->name('store');
        Route::get('/{produto}/edit', [EstoqueController::class, 'edit'])->name('edit');
        Route::put('/{produto}', [EstoqueController::class, 'update'])->name('update');
        Route::delete('/{produto}', [EstoqueController::class, 'destroy'])->name('destroy');
        Route::post('/{produto}/movimento', [EstoqueController::class, 'movimento'])->name('movimento');
    });

    Route::prefix('relatorios')->name('relatorios.')->group(function () {
        Route::get('/', [RelatorioController::class, 'index'])->name('index');
        Route::get('/receitas', [RelatorioController::class, 'receitasMensais'])->name('receitas');
        Route::get('/presenca-faltas', [RelatorioController::class, 'presencaFaltas'])->name('presenca-faltas');
        Route::get('/pacotes', [RelatorioController::class, 'pacotesSessoes'])->name('pacotes');
        Route::get('/exportar/{tipo}', [RelatorioController::class, 'exportarCsv'])->name('exportar');
    });

    Route::prefix('pacientes')->name('pacientes.')->group(function () {
        Route::get('/', [PacienteController::class, 'index'])->name('index');
        Route::get('/create', [PacienteController::class, 'create'])->name('create');
        Route::post('/', [PacienteController::class, 'store'])->name('store');

        Route::prefix('importar')->name('importar.')->group(function () {
            Route::get('/', [PacienteImportacaoController::class, 'create'])->name('create');
            Route::get('/modelo', [PacienteImportacaoController::class, 'modelo'])->name('modelo');
            Route::post('/', [PacienteImportacaoController::class, 'preview'])->name('preview');
            Route::get('/mapear', [PacienteImportacaoController::class, 'mapear'])->name('mapear');
            Route::post('/processar', [PacienteImportacaoController::class, 'importar'])->name('processar');
            Route::get('/resultado', [PacienteImportacaoController::class, 'resultado'])->name('resultado');
            Route::delete('/', [PacienteImportacaoController::class, 'cancelar'])->name('cancelar');
        });

        Route::get('/{paciente}', [PacienteController::class, 'show'])->name('show');
        Route::get('/{paciente}/edit', [PacienteController::class, 'edit'])->name('edit');
        Route::put('/{paciente}', [PacienteController::class, 'update'])->name('update');
        Route::delete('/{paciente}', [PacienteController::class, 'destroy'])->name('destroy');
        Route::get('/{paciente}/prontuario', [PacienteController::class, 'prontuario'])->name('prontuario');
        Route::get('/{paciente}/historico', [AtendimentoController::class, 'historico'])->name('historico');
        Route::post('/{paciente}/evolucoes', [PacienteController::class, 'storeEvolucao'])->name('evolucoes.store');
        Route::post('/{paciente}/anamneses', [PacienteController::class, 'storeAnamnese'])->name('anamneses.store');
        Route::post('/{paciente}/alertas', [PacienteController::class, 'storeAlerta'])->name('alertas.store');
        Route::delete('/{paciente}/alertas/{alerta}', [PacienteController::class, 'destroyAlerta'])->name('alertas.destroy');
        Route::post('/{paciente}/documentos', [PacienteController::class, 'storeDocumento'])->name('documentos.store');
        Route::get('/{paciente}/documentos/{documento}/download', [PacienteController::class, 'downloadDocumento'])->name('documentos.download');
        Route::delete('/{paciente}/documentos/{documento}', [PacienteController::class, 'destroyDocumento'])->name('documentos.destroy');

        Route::get('/{paciente}/odontograma', [OdontogramaController::class, 'index'])->middleware('vertical:odontograma')->name('odontograma.index');
        Route::get('/{paciente}/odontograma/dados', [OdontogramaController::class, 'dados'])->middleware('vertical:odontograma')->name('odontograma.dados');
        Route::get('/{paciente}/odontograma/historico', [OdontogramaController::class, 'historico'])->middleware('vertical:odontograma')->name('odontograma.historico');
        Route::put('/{paciente}/odontograma', [OdontogramaController::class, 'update'])->middleware('vertical:odontograma')->name('odontograma.settings');
        Route::put('/{paciente}/odontograma/dente/{dente}', [OdontogramaController::class, 'updateTooth'])->middleware('vertical:odontograma')->name('odontograma.tooth.update');
        Route::delete('/{paciente}/odontograma/dente/{dente}', [OdontogramaController::class, 'destroyTooth'])->middleware('vertical:odontograma')->name('odontograma.tooth.destroy');
        Route::post('/{paciente}/odontograma/dente/{dente}/condicoes', [OdontogramaController::class, 'storeCondition'])->middleware('vertical:odontograma')->name('odontograma.condition.store');
        Route::delete('/{paciente}/odontograma/dente/{dente}/condicoes/{condicao}', [OdontogramaController::class, 'destroyCondition'])->middleware('vertical:odontograma')->name('odontograma.condition.destroy');

        /**
         * Núcleo da fisioterapia: ficha de avaliação, evolução por sessão e
         * pacotes de sessões. As três rotas são exclusivas da vertical de
         * fisioterapia — uma clínica odontológica não abre nem digitando a URL.
         */
        Route::prefix('{paciente}/avaliacoes')->name('avaliacoes.')->middleware('vertical:fisioterapia')->group(function () {
            Route::get('/', [AvaliacaoController::class, 'index'])->name('index');
            Route::get('/create', [AvaliacaoController::class, 'create'])->name('create');
            Route::post('/', [AvaliacaoController::class, 'store'])->name('store');
            Route::get('/{avaliacao}', [AvaliacaoController::class, 'show'])->name('show');
            Route::get('/{avaliacao}/edit', [AvaliacaoController::class, 'edit'])->name('edit');
            Route::put('/{avaliacao}', [AvaliacaoController::class, 'update'])->name('update');
            Route::post('/{avaliacao}/finalizar', [AvaliacaoController::class, 'finalize'])->name('finalize');
        });

        /**
         * Evolução por sessão: um registro por atendimento, assinado pelo
         * profissional. Quando assinado, não muda mais.
         */
        Route::prefix('{paciente}/evolucoes-sessao')->name('evolucoes-sessao.')->middleware('vertical:fisioterapia')->group(function () {
            Route::get('/', [EvolucaoSessaoController::class, 'index'])->name('index');
            Route::get('/create', [EvolucaoSessaoController::class, 'create'])->name('create');
            Route::post('/', [EvolucaoSessaoController::class, 'store'])->name('store');
            Route::get('/{evolucao}/edit', [EvolucaoSessaoController::class, 'edit'])->name('edit');
            Route::put('/{evolucao}', [EvolucaoSessaoController::class, 'update'])->name('update');
            Route::post('/{evolucao}/finalizar', [EvolucaoSessaoController::class, 'finalize'])->name('finalize');
        });

        /**
         * Pacotes de sessões: o consumo é transacional e nunca deixa o saldo
         * negativo, mesmo com duas atendentes marcando sessão ao mesmo tempo.
         */
        Route::prefix('{paciente}/pacotes')->name('pacotes.')->middleware('vertical:fisioterapia')->group(function () {
            Route::get('/', [PacoteSessaoController::class, 'index'])->name('index');
            Route::get('/create', [PacoteSessaoController::class, 'create'])->name('create');
            Route::post('/', [PacoteSessaoController::class, 'store'])->name('store');
            Route::get('/{pacote}', [PacoteSessaoController::class, 'show'])->name('show');
            Route::post('/{pacote}/consumir', [PacoteSessaoController::class, 'consumir'])->name('consumir');
            Route::delete('/{pacote}', [PacoteSessaoController::class, 'destroy'])->name('destroy');
        });

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

        Route::get('/{paciente}/orcamentos', [OrcamentoController::class, 'index'])->name('orcamentos.index');
        Route::post('/{paciente}/orcamentos', [OrcamentoController::class, 'store'])->name('orcamentos.store');
        Route::get('/{paciente}/orcamentos/{orcamento}', [OrcamentoController::class, 'show'])->name('orcamentos.show');
        Route::put('/{paciente}/orcamentos/{orcamento}', [OrcamentoController::class, 'update'])->name('orcamentos.update');
        Route::delete('/{paciente}/orcamentos/{orcamento}', [OrcamentoController::class, 'destroy'])->name('orcamentos.destroy');
        Route::post('/{paciente}/orcamentos/{orcamento}/itens', [OrcamentoController::class, 'itemStore'])->name('orcamentos.items.store');
        Route::delete('/{paciente}/orcamentos/{orcamento}/itens/{item}', [OrcamentoController::class, 'itemDestroy'])->name('orcamentos.items.destroy');
    });

    Route::prefix('admin')->name('admin.')->middleware('can:admin.view')->group(function () {
        Route::resource('usuarios', UserController::class)->except(['show']);
        Route::get('perfis', [RoleController::class, 'index'])->name('perfis.index');
        Route::get('perfis/{role}/edit', [RoleController::class, 'edit'])->name('perfis.edit');
        Route::put('perfis/{role}', [RoleController::class, 'update'])->name('perfis.update');
        Route::get('permissoes', [PermissionController::class, 'index'])->name('permissoes.index');
        Route::get('clinica', [ClinicaController::class, 'edit'])->name('clinica.edit');
        Route::put('clinica', [ClinicaController::class, 'update'])->name('clinica.update');
        Route::get('evolution/qr', [EvolutionQrController::class, 'show'])->name('evolution.qr');
        Route::get('test/evolution/qr', [EvolutionQrController::class, 'show'])->name('test.evolution.qr');

        Route::get('fiscal', [FiscalController::class, 'index'])->name('fiscal.index');
        Route::put('fiscal/config', [FiscalController::class, 'updateConfig'])->name('fiscal.config.update');
        Route::post('fiscal/register', [FiscalController::class, 'register'])->name('fiscal.register');
        Route::post('fiscal/readiness', [FiscalController::class, 'syncReadiness'])->name('fiscal.readiness');

        Route::prefix('fiscal/nfse')->name('fiscal.nfse.')->group(function () {
            Route::get('/', [FiscalController::class, 'nfseIndex'])->name('index');
            Route::get('/{nfse}', [FiscalController::class, 'nfseShow'])->name('show');
            Route::post('/{nfse}/preview', [FiscalController::class, 'nfsePreview'])->name('preview');
            Route::post('/{nfse}/emit', [FiscalController::class, 'nfseEmit'])->name('emit');
            Route::post('/{nfse}/cancel', [FiscalController::class, 'nfseCancel'])->name('cancel');
            Route::get('/{nfse}/pdf', [FiscalController::class, 'nfsePdf'])->name('pdf');
            Route::get('/{nfse}/xml', [FiscalController::class, 'nfseXml'])->name('xml');
        });

        Route::get('auditoria', [AuditLogController::class, 'index'])->name('auditoria.index');
    });
});

require __DIR__.'/auth.php';

// Módulo Fiscal
Route::middleware(['auth', 'tenant'])->group(function () {
    Route::prefix('fiscal')->name('fiscal.')->controller(App\Http\Controllers\FiscalController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/nova', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::get('/{notaFiscal}', 'show')->name('show');
        Route::put('/{notaFiscal}', 'update')->name('update');
        Route::delete('/{notaFiscal}', 'destroy')->name('destroy');
        Route::post('/{notaFiscal}/emitir', 'emitir')->name('emitir');
        Route::post('/{notaFiscal}/cancelar', 'cancelar')->name('cancelar');
        Route::post('/{notaFiscal}/consultar', 'consultar')->name('consultar');
        Route::get('/{notaFiscal}/xml', 'xml')->name('xml');
        Route::get('/{notaFiscal}/imprimir', 'imprimir')->name('imprimir');
    });
});

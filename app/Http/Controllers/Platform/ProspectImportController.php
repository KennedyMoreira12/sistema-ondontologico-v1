<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\ProspectImportRequest;
use App\Models\Central\AdminUser;
use App\Services\Platform\Prospeccao\ProspectImporter;
use App\Services\Platform\Prospeccao\ProspectSpreadsheetException;
use App\Services\Platform\Prospeccao\ProspectSpreadsheetReader;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Importação de contatos por planilha (.xlsx/.csv) na área de prospecção.
 *
 * O fluxo é em duas etapas, para o operador conferir antes de gravar:
 *
 *   1. POST  `importar`          -> lê o arquivo, monta a prévia e guarda na sessão;
 *   2. GET   `importar/previa`   -> mostra a prévia;
 *   3. POST  `importar/confirmar`-> executa a importação de verdade;
 *      POST  `importar/cancelar` -> descarta a prévia.
 *
 * A prévia não grava nada (ver `ProspectImporter::preview`), e a importação é
 * idempotente: reenviar o mesmo arquivo não cria contatos repetidos.
 */
class ProspectImportController extends Controller
{
    private const SESSION_KEY = 'prospeccao.importacao';

    public function __construct(
        private readonly ProspectSpreadsheetReader $reader,
        private readonly ProspectImporter $importer,
    ) {}

    public function create(): View
    {
        return view('platform.prospeccao.import');
    }

    public function store(ProspectImportRequest $request): RedirectResponse
    {
        $arquivo = $request->file('arquivo');

        try {
            $source = $this->reader->read($arquivo->getRealPath(), $arquivo->getClientOriginalName());
        } catch (ProspectSpreadsheetException $e) {
            return back()->withErrors(['arquivo' => $e->getMessage()])->withInput();
        }

        $request->session()->put(self::SESSION_KEY, [
            'admin_id' => $request->user()?->getKey(),
            'source' => $source,
            'preview' => $this->importer->preview($source),
            'criado_em' => now()->toIso8601String(),
        ]);

        return redirect()->route('painel.prospeccao.importar.previa');
    }

    public function preview(Request $request): View|RedirectResponse
    {
        $dados = $this->dadosDaSessao($request);

        if ($dados === null) {
            return redirect()
                ->route('painel.prospeccao.importar')
                ->with('warning', 'Nenhuma prévia pendente. Envie o arquivo novamente.');
        }

        return view('platform.prospeccao.import_preview', [
            'arquivo' => (string) ($dados['source']['source_file'] ?? 'planilha'),
            'preview' => $dados['preview'],
        ]);
    }

    public function confirm(Request $request): RedirectResponse
    {
        $dados = $this->dadosDaSessao($request);

        if ($dados === null) {
            return redirect()
                ->route('painel.prospeccao.importar')
                ->with('warning', 'Nenhuma prévia pendente. Envie o arquivo novamente.');
        }

        $request->session()->forget(self::SESSION_KEY);

        $import = $this->importer->import($dados['source'], $request->user());

        $existentes = $import->updated + count((array) ($import->report['inalterados'] ?? []));

        return redirect()
            ->route('painel.prospeccao.index')
            ->with('success', sprintf(
                'Importação de "%s" concluída: %d novo(s), %d duplicado(s) fundido(s), %d já existente(s), %d com pendência(s), %d rejeitado(s).',
                (string) ($dados['source']['source_file'] ?? 'planilha'),
                $import->imported,
                $import->duplicated,
                $existentes,
                $import->pending,
                $import->rejected,
            ));
    }

    public function cancel(Request $request): RedirectResponse
    {
        $request->session()->forget(self::SESSION_KEY);

        return redirect()
            ->route('painel.prospeccao.index')
            ->with('success', 'Importação cancelada. Nenhum contato foi gravado.');
    }

    /**
     * Recupera a prévia guardada, mas só se ela for do admin logado — assim uma
     * sessão reaproveitada no mesmo navegador não confirma a prévia de outro.
     *
     * @return array{admin_id:int|null,source:array<string,mixed>,preview:array<string,mixed>,criado_em:string}|null
     */
    private function dadosDaSessao(Request $request): ?array
    {
        $dados = $request->session()->get(self::SESSION_KEY);

        if (! is_array($dados)) {
            return null;
        }

        $admin = $request->user();

        if (! $admin instanceof AdminUser || ($dados['admin_id'] ?? null) !== $admin->getKey()) {
            return null;
        }

        return $dados;
    }
}

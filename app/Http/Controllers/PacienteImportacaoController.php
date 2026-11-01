<?php

namespace App\Http\Controllers;

use App\Services\AuditLogger;
use App\Services\Pacientes\ImportadorPacientes;
use App\Services\Pacientes\PlanilhaException;
use App\Services\Pacientes\PlanilhaReader;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Importação de pacientes por planilha em três etapas.
 *
 * O arquivo enviado não é lido de novo a cada POST: ele é convertido uma vez
 * para JSON em `storage/app/private/importacoes` e as etapas seguintes usam
 * esse JSON. Assim a tela de conferência não depende de reparsear o .xlsx e o
 * que foi mostrado ao usuário é exatamente o que será importado.
 *
 * O estado do fluxo inteiro fica na sessão (id do arquivo, cabeçalho, amostra e
 * mapa). Só o resumo final é guardado à parte para a tela de resultado.
 */
class PacienteImportacaoController extends Controller
{
    private const SESSAO = 'pacientes.importacao';

    private const RESULTADO = 'pacientes.importacao.resultado';

    public function create(): View
    {
        $this->authorize('pacientes.create');

        $this->limparArquivosAntigos();
        session()->forget(self::RESULTADO);

        return view('pacientes.importar');
    }

    /**
     * Modelo CSV com as colunas que o sistema reconhece sozinho. Serve para o
     * usuário que ainda vai montar a planilha, não para quem já tem a dele.
     */
    public function modelo(): StreamedResponse
    {
        $this->authorize('pacientes.create');

        $cabecalho = [
            'nome', 'cpf', 'nascimento', 'sexo', 'email', 'celular', 'telefone',
            'cep', 'endereco', 'numero', 'complemento', 'bairro', 'cidade', 'uf',
            'responsavel', 'observacoes',
        ];

        $exemplo = [
            'Maria da Silva', '123.456.789-00', '10/05/1985', 'feminino', 'maria@email.com',
            '38999990000', '38988880000', '39480000', 'Rua A', '123', 'Apto 1', 'Centro',
            'Januária', 'MG', '', 'Paciente importado da planilha',
        ];

        return response()->streamDownload(function () use ($cabecalho, $exemplo): void {
            $saida = fopen('php://output', 'w');
            fwrite($saida, "\xEF\xBB\xBF"); // BOM para o Excel abrir em UTF-8
            fputcsv($saida, $cabecalho, ';', '"', '');
            fputcsv($saida, $exemplo, ';', '"', '');
            fclose($saida);
        }, 'modelo-importacao-pacientes.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function preview(Request $request, PlanilhaReader $reader, ImportadorPacientes $importador): RedirectResponse
    {
        $this->authorize('pacientes.create');

        $request->validate([
            'arquivo' => ['required', 'file', 'max:5120'],
        ], [
            'arquivo.required' => 'Escolha a planilha que deseja importar.',
            'arquivo.file' => 'Não foi possível receber o arquivo. Envie novamente.',
            'arquivo.max' => 'A planilha deve ter no máximo 5 MB.',
        ]);

        $arquivo = $request->file('arquivo');
        $extensao = strtolower($arquivo->getClientOriginalExtension());

        if (! in_array($extensao, ['xlsx', 'csv', 'txt'], true)) {
            return back()->with('error', 'Formato não suportado. Envie a planilha em .xlsx ou .csv. Se ela for .xls antigo, abra no Excel e salve como .xlsx.');
        }

        $this->limparSessaoAnterior();

        $id = (string) Str::uuid();
        $caminho = 'importacoes/'.$id.'.'.$extensao;
        $caminhoDados = 'importacoes/'.$id.'.json';
        $disco = Storage::disk('local');

        $arquivo->storeAs('importacoes', $id.'.'.$extensao, 'local');

        try {
            $tabela = $reader->ler($disco->path($caminho), $extensao);
        } catch (PlanilhaException $e) {
            $disco->delete($caminho);

            return back()->with('error', $e->getMessage());
        }

        $json = json_encode($tabela, JSON_UNESCAPED_UNICODE);

        if ($json === false || ! $disco->put($caminhoDados, $json)) {
            $disco->delete($caminho);

            return back()->with('error', 'Não foi possível guardar a planilha para conferência. Tente novamente.');
        }

        session([self::SESSAO => [
            'id' => $id,
            'arquivo' => $caminho,
            'dados' => $caminhoDados,
            'nome_original' => $arquivo->getClientOriginalName(),
            'extensao' => $extensao,
            'cabecalho' => $tabela['cabecalho'],
            'total_linhas' => count($tabela['linhas']),
            'truncada' => $tabela['truncada'],
            'amostra' => array_slice($tabela['linhas'], 0, 5),
            'mapa' => $importador->autoMapear($tabela['cabecalho']),
        ]]);

        return redirect()->route('pacientes.importar.mapear');
    }

    public function mapear(): View|RedirectResponse
    {
        $this->authorize('pacientes.create');

        $estado = $this->estado();

        if ($estado === null) {
            return redirect()->route('pacientes.importar.create')
                ->with('error', 'Envie uma planilha para começar a importação.');
        }

        $grupos = [];

        foreach (ImportadorPacientes::CAMPOS as $campo => $definicao) {
            $grupos[$definicao['grupo']][$campo] = $definicao;
        }

        return view('pacientes.importar-mapear', compact('estado', 'grupos'));
    }

    public function importar(Request $request, ImportadorPacientes $importador): RedirectResponse
    {
        $this->authorize('pacientes.create');

        $estado = $this->estado();

        if ($estado === null) {
            return redirect()->route('pacientes.importar.create')
                ->with('error', 'Envie uma planilha para começar a importação.');
        }

        $request->validate([
            'mapa' => ['nullable', 'array'],
            'mapa.*' => ['nullable', 'integer', 'min:0', 'max:'.(count($estado['cabecalho']) - 1)],
        ], [
            'mapa.*.integer' => 'Escolha uma coluna válida.',
            'mapa.*.min' => 'Escolha uma coluna válida.',
            'mapa.*.max' => 'Escolha uma coluna válida.',
        ]);

        $mapa = [];

        foreach (array_keys(ImportadorPacientes::CAMPOS) as $campo) {
            $coluna = $request->input('mapa.'.$campo);

            if ($coluna !== null && $coluna !== '') {
                $mapa[$campo] = (int) $coluna;
            }
        }

        if (! isset($mapa['nome'])) {
            return back()
                ->withInput()
                ->withErrors(['mapa.nome' => 'Escolha a coluna que tem o nome do paciente.']);
        }

        try {
            $tabela = $this->lerTabela($estado);
        } catch (PlanilhaException $e) {
            $this->limparSessaoAnterior();

            return redirect()->route('pacientes.importar.create')->with('error', $e->getMessage());
        }

        $resultado = $importador->importar(
            $tabela['linhas'],
            $mapa,
            (int) auth()->user()->clinica_id,
        );

        $resultado['arquivo'] = $estado['nome_original'];
        $resultado['total_linhas'] = $estado['total_linhas'];
        $resultado['truncada'] = $estado['truncada'];

        AuditLogger::log('importar', 'pacientes', null, depois: [
            'arquivo' => $estado['nome_original'],
            'criados' => $resultado['criados'],
            'ignorados' => $resultado['ignorados_total'],
            'erros' => $resultado['erros_total'],
        ]);

        $this->apagarArquivos($estado);
        session()->forget(self::SESSAO);
        session([self::RESULTADO => $resultado]);

        return redirect()->route('pacientes.importar.resultado');
    }

    public function resultado(): View|RedirectResponse
    {
        $this->authorize('pacientes.create');

        $resultado = session(self::RESULTADO);

        if (! is_array($resultado)) {
            return redirect()->route('pacientes.importar.create')
                ->with('error', 'Nenhuma importação para exibir.');
        }

        return view('pacientes.importar-resultado', compact('resultado'));
    }

    public function cancelar(): RedirectResponse
    {
        $this->authorize('pacientes.create');

        $this->limparSessaoAnterior();

        return redirect()->route('pacientes.index')->with('success', 'Importação cancelada.');
    }

    /**
     * @return array<string, mixed>|null
     */
    private function estado(): ?array
    {
        $estado = session(self::SESSAO);

        if (! is_array($estado)) {
            return null;
        }

        foreach (['id', 'arquivo', 'dados', 'nome_original', 'cabecalho', 'total_linhas', 'amostra'] as $chave) {
            if (! array_key_exists($chave, $estado)) {
                return null;
            }
        }

        return $estado;
    }

    /**
     * @param  array<string, mixed>  $estado
     * @return array{cabecalho: list<string>, linhas: list<list<string>>, truncada: bool}
     */
    private function lerTabela(array $estado): array
    {
        $caminho = $estado['dados'];

        if (! is_string($caminho) || ! str_starts_with($caminho, 'importacoes/')) {
            throw new PlanilhaException('A planilha desta importação não está mais disponível. Envie o arquivo novamente.');
        }

        $disco = Storage::disk('local');
        $conteudo = $disco->exists($caminho) ? $disco->get($caminho) : null;
        $tabela = is_string($conteudo) ? json_decode($conteudo, true) : null;

        if (! is_array($tabela) || ! isset($tabela['linhas']) || ! is_array($tabela['linhas'])) {
            throw new PlanilhaException('A planilha desta importação não está mais disponível. Envie o arquivo novamente.');
        }

        return $tabela;
    }

    private function limparSessaoAnterior(): void
    {
        $estado = $this->estado();

        if ($estado !== null) {
            $this->apagarArquivos($estado);
        }

        session()->forget([self::SESSAO, self::RESULTADO]);
    }

    /**
     * @param  array<string, mixed>  $estado
     */
    private function apagarArquivos(array $estado): void
    {
        $disco = Storage::disk('local');

        foreach ([$estado['arquivo'] ?? null, $estado['dados'] ?? null] as $caminho) {
            if (is_string($caminho) && str_starts_with($caminho, 'importacoes/')) {
                $disco->delete($caminho);
            }
        }
    }

    /**
     * Arquivo enviado e abandonado no meio da conferência não precisa ficar
     * para sempre em disco. Um dia é folga suficiente para quem deixou a aba
     * aberta e voltou depois.
     */
    private function limparArquivosAntigos(): void
    {
        $disco = Storage::disk('local');
        $limite = now()->subDay()->getTimestamp();
        $atual = session(self::SESSAO.'.id');

        foreach ($disco->files('importacoes') as $arquivo) {
            if ($atual !== null && str_contains($arquivo, (string) $atual)) {
                continue;
            }

            if ($disco->lastModified($arquivo) < $limite) {
                $disco->delete($arquivo);
            }
        }
    }
}

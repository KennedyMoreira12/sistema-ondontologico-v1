<?php

namespace App\Http\Controllers;

use App\Models\Paciente;
use App\Models\Professional;
use App\Services\Branding\MalheiroBranding;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * Controller para geração de documentos da Clínica Malheiro.
 *
 * Acesso restrito ao tenant atual (isolamento multi-tenant garantido pelo middleware ResolveTenant).
 * A identidade Malheiro só é aplicada quando clinic_id = 1.
 */
class DocumentController extends Controller
{
    public function __construct(
        protected MalheiroBranding $branding,
    ) {}

    /**
     * Verifica se o tenant atual é a Clínica Malheiro.
     * Se não for, redireciona com erro 403.
     */
    protected function ensureMalheiro(): void
    {
        if (! $this->branding->isMalheiro()) {
            abort(403, 'Documentos Malheiro disponíveis apenas para a Clínica Malheiro.');
        }
    }

    /**
     * Página de visualização do Papel Timbrado.
     */
    public function papelTimbrado(): View
    {
        $this->ensureMalheiro();
        $this->authorize('documentos.view');

        return view('documents.malheiro.papel-timbrado', array_merge(
            $this->branding->dadosParaDocumento(),
            ['titulo' => 'Papel Timbrado']
        ));
    }

    /**
     * Gera PDF do Papel Timbrado.
     */
    public function papelTimbradoPdf(): Response
    {
        $this->ensureMalheiro();
        $this->authorize('documentos.generate');

        $pdf = Pdf::loadView('documents.malheiro.papel-timbrado', array_merge(
            $this->branding->dadosParaDocumento(),
            ['titulo' => 'Papel Timbrado']
        ))->setPaper('a4', 'portrait');

        return $pdf->stream('papel-timbrado-malheiro.pdf');
    }

    /**
     * Página de visualização da Guia de Cuidados.
     */
    public function guiaCuidados(): View
    {
        $this->ensureMalheiro();
        $this->authorize('documentos.view');

        return view('documents.malheiro.guia-cuidados', array_merge(
            $this->branding->dadosParaDocumento(),
            ['titulo' => 'Guia de Cuidados Odontológicos']
        ));
    }

    /**
     * Gera PDF da Guia de Cuidados.
     */
    public function guiaCuidadosPdf(): Response
    {
        $this->ensureMalheiro();
        $this->authorize('documentos.generate');

        $pdf = Pdf::loadView('documents.malheiro.guia-cuidados', array_merge(
            $this->branding->dadosParaDocumento(),
            ['titulo' => 'Guia de Cuidados Odontológicos']
        ))->setPaper('a4', 'portrait');

        return $pdf->stream('guia-cuidados-malheiro.pdf');
    }

    /**
     * Formulário para criar Atestado Odontológico.
     */
    public function atestadoCreate(Request $request): View
    {
        $this->ensureMalheiro();
        $this->authorize('documentos.view');

        // Busca pacientes e profissionais do tenant atual para preencher selects.
        // O isolamento é a conexão `tenant`; `clinica_id` é trilha de auditoria
        // e não entra em filtro — os pacientes antigos da Malheiro têm 0/NULL.
        $pacientes = Paciente::query()
            ->orderBy('nome')
            ->get(['id', 'nome', 'cpf']);

        $profissionais = Professional::query()
            ->where('is_active', true)
            ->orderBy('nome')
            ->get(['id', 'nome', 'cro', 'registro_profissional', 'conselho_regional']);

        return view('documents.malheiro.atestado-create', array_merge(
            $this->branding->dadosParaDocumento(),
            [
                'titulo' => 'Emitir Atestado Odontológico',
                'pacientes' => $pacientes,
                'profissionais' => $profissionais,
            ]
        ));
    }

    /**
     * Gera PDF do Atestado Odontológico com dados preenchidos.
     */
    public function atestadoPdf(Request $request): Response
    {
        $this->ensureMalheiro();
        $this->authorize('documentos.generate');

        $validated = $request->validate([
            'paciente_id' => ['required', 'integer', 'exists:tenant.pacientes,id'],
            'profissional_id' => ['required', 'integer', 'exists:tenant.professionals,id'],
            'atendimento_data' => ['required', 'date'],
            'atendimento_hora' => ['nullable', 'date_format:H:i'],
            'dias_repouso' => ['required', 'integer', 'min:1', 'max:90'],
            'local' => ['nullable', 'string', 'max:255'],
        ]);

        // Carrega relacionamentos
        $paciente = Paciente::query()->findOrFail($validated['paciente_id']);
        $profissional = Professional::query()->findOrFail($validated['profissional_id']);

        // Formata data para exibição
        $dataFormatada = Carbon::parse($validated['atendimento_data'])->format('d/m/Y');
        $horaFormatada = $validated['atendimento_hora']
            ? Carbon::parse($validated['atendimento_hora'])->format('H:i')
            : '';
        $dataEmissao = now()->format('d/m/Y');
        $local = $validated['local'] ?? $this->branding->dadosRodape()['cidade'].'/'.$this->branding->dadosRodape()['estado'];

        $pdf = Pdf::loadView('documents.malheiro.atestado-odontologico', array_merge(
            $this->branding->dadosParaDocumento(),
            [
                'titulo' => 'Atestado Odontológico',
                'paciente_nome' => $paciente->nome,
                'paciente_cpf' => $paciente->cpf ?: $paciente->rg,
                'atendimento_data' => $dataFormatada,
                'atendimento_hora' => $horaFormatada,
                'dias_repouso' => $validated['dias_repouso'],
                'local' => $local,
                'data_emissao' => $dataEmissao,
                'nome_profissional' => $profissional->nome,
                'cro' => $profissional->registroConselho(),
                'especialidades' => $profissional->specialties->pluck('name')->toArray(),
            ]
        ))->setPaper('a4', 'portrait');

        $filename = "atestado-odontologico-{$paciente->nome}-{$dataFormatada}.pdf";
        $filename = str_replace([' ', '/'], '-', $filename);

        return $pdf->stream($filename);
    }

    /**
     * API para buscar dados do paciente (para preenchimento automático via AJAX).
     */
    public function atestadoPacienteData(Request $request, Paciente $paciente): JsonResponse
    {
        $this->ensureMalheiro();
        $this->authorize('documentos.view');

        // O paciente já foi resolvido pela conexão `tenant` (route model
        // binding), então ele pertence necessariamente a esta clínica.
        return response()->json([
            'nome' => $paciente->nome,
            'cpf' => $paciente->cpf,
            'rg' => $paciente->rg,
        ]);
    }
}

<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\ProspectCommercialRequest;
use App\Http\Requests\Platform\ProspectUpdateRequest;
use App\Models\Central\Prospect;
use App\Models\Central\ProspectMessageTemplate;
use App\Services\Platform\PlatformLogger;
use Illuminate\Support\Collection;
use App\Services\Platform\Prospeccao\ProspectWhatsAppService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\MessageBag;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

class ProspectController extends Controller
{
    private const PER_PAGE = 25;

    private const PENDENTES = ['unconfirmed', 'incomplete', 'invalid'];

    private const CANAIS_COMERCIAIS = ['whatsapp'];

    public function __construct(protected ProspectWhatsAppService $whatsapp) {}

    public function index(Request $request): View
    {
        $busca = trim($request->string('busca')->toString());
        $categoria = $request->string('category')->toString();
        $cidade = $request->string('city')->toString();
        $prioridade = $request->string('priority')->toString();
        $telefone = $request->string('phone_status')->toString();
        $status = $request->string('status')->toString();

        $base = $this->baseQuery($busca)
            ->when($categoria !== '', fn (Builder $query) => $query->where('category', $categoria))
            ->when($cidade !== '', fn (Builder $query) => $query->where('city', $cidade))
            ->when($prioridade !== '', fn (Builder $query) => $query->where('priority', $prioridade))
            ->when($telefone !== '', fn (Builder $query) => $query->where('phone_status', $telefone));

        $resumo = [
            'total' => (clone $base)->count(),
            'a_validar' => (clone $base)->whereIn('phone_status', self::PENDENTES)->count(),
            'nao_contatado' => (clone $base)->where('status', 'nao_contatado')->count(),
            'negociacao' => (clone $base)->where('status', 'negociacao')->count(),
            'reuniao_marcada' => (clone $base)->where('status', 'reuniao_marcada')->count(),
            'cliente' => (clone $base)->where('status', 'cliente')->count(),
        ];

        $prospects = (clone $base)
            ->when($status !== '', fn (Builder $query) => $query->where('status', $status))
            ->withCount('origins')
            ->orderBy('name')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return view('platform.prospeccao.index', [
            'prospects' => $prospects,
            'resumo' => $resumo,
            'whatsapp' => $this->whatsapp->storedSummary($this->whatsapp->setting()),
            'compartilhados' => $this->telefonesCompartilhados($prospects->getCollection()),
            'categorias' => Prospect::query()->whereNotNull('category')->distinct()->orderBy('category')->pluck('category'),
            'cidades' => Prospect::query()->whereNotNull('city')->distinct()->orderBy('city')->pluck('city'),
            'statuses' => config('prospeccao.statuses'),
            'prioridades' => config('prospeccao.priorities'),
            'phoneStatuses' => config('prospeccao.phone_statuses'),
            'filtros' => [
                'busca' => $busca,
                'category' => $categoria,
                'city' => $cidade,
                'priority' => $prioridade,
                'phone_status' => $telefone,
                'status' => $status,
            ],
        ]);
    }

    public function show(Prospect $prospect): View
    {
        return view('platform.prospeccao.show', [
            'prospect' => $prospect->load(['origins', 'interactions']),
            'statuses' => config('prospeccao.statuses'),
            'prioridades' => config('prospeccao.priorities'),
            'canais' => config('prospeccao.channels'),
            'templates' => ProspectMessageTemplate::query()->activeOrdered()->get(),
            'podeEnviarComercial' => $prospect->podeReceberComercial(),
            'botStatus' => $prospect->botStatus(),
            'estaBloqueado' => $prospect->estaBloqueado(),
        ]);
    }

    public function origem(string $source): BinaryFileResponse
    {
        $documentos = (array) config('prospeccao.source_documents', []);
        $documento = $documentos[$source] ?? null;

        abort_if($documento === null, 404);

        $caminho = storage_path('app/prospeccao/'.$documento);

        abort_unless(is_file($caminho), 404);

        return response()->download($caminho, $documento);
    }

    public function comercial(ProspectCommercialRequest $request): JsonResponse
    {
        $dados = $request->validated();
        $prospectId = (int) $dados['prospect_id'];
        $templateId = (int) ($dados['template_id'] ?? 0);
        $mensagem = trim((string) ($dados['mensagem'] ?? ''));
        $acao = $dados['acao'];

        $prospect = Prospect::findOrFail($prospectId);

        if ($acao !== 'pausar_bot' && $acao !== 'retomar_bot' && $prospect->estaPausadoBot()) {
            return response()->json([
                'status' => 'bloqueado',
                'motivo' => 'Bot de prospecção pausado para este contato.',
                'bot_status' => 'Pausado',
            ], 409);
        }

        if ($prospect->estaBloqueado() && $acao !== 'pausar_bot' && $acao !== 'retomar_bot') {
            return response()->json([
                'status' => 'bloqueado',
                'motivo' => 'Contato solicitou não receber mensagens (opted_out).',
                'bot_status' => $prospect->botStatus(),
                'pode_receber' => false,
            ], 409);
        }

        $setting = $this->whatsapp->setting();
        if ($setting === null) {
            return response()->json([
                'status' => 'erro',
                'motivo' => 'Nenhuma configuração de instância de prospecção encontrada. Crie a instância lj-prospeccao primeiro.',
            ], 409);
        }

        if ($setting->status !== 'connected') {
            return response()->json([
                'status' => 'erro',
                'motivo' => 'A instância de prospecção não está conectada. Status: ' . $this->whatsapp->statusLabel($setting->status),
            ], 409);
        }

        if ($acao === 'pausar_bot') {
            $prospect->bot_pause = true;
            $prospect->bot_paused_at = now();
            $prospect->save();

            return response()->json([
                'status' => 'pausado',
                'bot_status' => 'Pausado',
                'bot_paused_at' => $prospect->bot_paused_at->toIso8601String(),
            ]);
        }

        if ($acao === 'retomar_bot') {
            $prospect->bot_pause = false;
            $prospect->bot_paused_at = null;
            $prospect->save();

            return response()->json([
                'status' => 'retomado',
                'bot_status' => 'Ativo',
            ]);
        }

        if ($acao === 'preparar') {
            $template = \App\Models\Central\ProspectMessageTemplate::find($templateId);
            $templateBody = $template?->body ?? '';

            $corpoFinal = $this->substituirPlaceholders($prospect, $mensagem ?? '', $templateBody);

            return response()->json([
                'status' => 'preparado',
                'prospect_id' => $prospect->id,
                'prospect_nome' => $prospect->name,
                'telefone' => $prospect->phone_original ?? '—',
                'template_id' => $templateId,
                'template_nome' => $template?->name ?? 'Personalizado',
                'corpo' => $corpoFinal,
                'mensagem_bruta' => $mensagem ?? '',
                'destinatario_valido' => $prospect->hasContactablePhone(),
                'pode_enviar' => $prospect->podeReceberComercial(),
            ]);
        }

        $template = \App\Models\Central\ProspectMessageTemplate::find($templateId);
        $corpoTemplate = $template?->body ?? '';

        $corpoFinal = $this->substituirPlaceholders($prospect, $mensagem, $corpoTemplate);

        // Revalidação da prévia: o corpo aprovado na tela precisa bater, byte a
        // byte, com o conteúdo recalculado agora (modelo/mensagem + dados atuais).
        $corpoAprovado = (string) ($dados['corpo_aprovado'] ?? '');
        if ($corpoAprovado === '' || $corpoAprovado !== $corpoFinal) {
            return response()->json([
                'status' => 'desatualizado',
                'motivo' => 'A prévia está desatualizada em relação ao conteúdo atual. Gere a prévia novamente antes de enviar.',
            ], 409);
        }

        // Permissões e destinatário revalidados no servidor (telefone, opt-out,
        // pausa do bot), independentemente do que o navegador afirma.
        if (! $prospect->podeReceberComercial()) {
            return response()->json([
                'status' => 'bloqueado',
                'motivo' => 'Este contato não pode receber mensagem comercial no momento (verifique telefone, opt-out e pausa do bot).',
                'bot_status' => $prospect->botStatus(),
                'pode_receber' => false,
            ], 409);
        }

        $numero = $prospect->phone_normalized;
        if (! $numero) {
            return response()->json([
                'status' => 'erro',
                'motivo' => 'Prospect sem número normalizado válido.',
            ], 400);
        }

        // Anti-duplicidade: só um envio por contato por vez. Cliques repetidos
        // (ou requisições concorrentes) encontram a chave e são recusados.
        $lockKey = 'prospect-comercial-envio:'.$prospect->id;
        if (! Cache::add($lockKey, now()->timestamp, 20)) {
            return response()->json([
                'status' => 'duplicado',
                'motivo' => 'Já existe um envio em andamento para este contato. Aguarde alguns segundos e tente novamente.',
            ], 429);
        }

        try {
            $resultado = $this->whatsapp->sendText($numero, $corpoFinal);

            if (! $resultado['ok']) {
                $motivo = $resultado['categoria'] === 'sem_whatsapp'
                    ? 'O número '.($prospect->phone_original ?? $numero).' não possui WhatsApp. Confira o telefone do prospect ou use outro canal.'
                    : $resultado['motivo'];

                // Detalhe técnico (sem token) só no log; mensagem clara ao admin.
                \Log::warning('Falha ao enviar mensagem comercial (prospecção)', [
                    'prospect_id' => $prospect->id,
                    'http' => $resultado['http'],
                    'categoria' => $resultado['categoria'],
                    'resposta' => mb_substr((string) json_encode($resultado['dados'], JSON_UNESCAPED_UNICODE), 0, 300),
                ]);

                try {
                    $prospect->registrarInteracaoComercial('erro', 'evolution_'.$resultado['categoria'], $motivo);
                } catch (\Throwable $e2) {
                    \Log::error('Falha ao registrar interacao de erro', ['prospect_id' => $prospect->id, 'erro' => $e2->getMessage()]);
                }

                return response()->json([
                    'status' => 'erro',
                    'categoria' => $resultado['categoria'],
                    'motivo' => $motivo,
                    'telefone' => $prospect->phone_original ?? '—',
                ], $resultado['categoria'] === 'sem_whatsapp' ? 409 : 502);
            }

            $envioData = $resultado['dados'];

            $chaveIdempotente = 'comercial-'.$prospect->id.'-'.time().'-'.md5($corpoFinal.$numero);

            try {
                $prospect->registrarInteracaoComercial(
                    'enviado',
                    $envioData['key']['id'] ?? 'desconhecido',
                    'Template: '.($template?->name ?? 'personalizado').'; Corpo: '.strlen($corpoFinal).' chars; Dest: '.$numero
                );

                PlatformLogger::log('mensagem_comercial_enviada', 'prospect', $prospect->id, [
                    'prospect' => $prospect->name,
                    'template' => $template?->name ?? 'personalizado',
                    'chave' => substr($chaveIdempotente, 0, 16).'...',
                    'destinatario' => substr($numero, -4),
                ]);
            } catch (\Throwable $e2) {
                \Log::error('Falha ao registrar interacao comercial', [
                    'prospect_id' => $prospect->id,
                    'erro' => $e2->getMessage(),
                ]);
            }

            return response()->json([
                'status' => 'enviado',
                'prospect_id' => $prospect->id,
                'prospect_nome' => $prospect->name,
                'telefone' => $prospect->phone_original ?? '—',
                'corpo_enviado' => $corpoFinal,
                'template' => $template?->name ?? 'personalizado',
                'chave' => $envioData['key']['id'] ?? null,
                'duracao_estimada' => 'Imediato',
            ]);
        } finally {
            Cache::forget($lockKey);
        }
    }

    public function update(ProspectUpdateRequest $request, Prospect $prospect): RedirectResponse
    {
        $dados = $request->validated();

        $antes = $prospect->only([
            'status', 'priority', 'next_follow_up_at',
            'notes', 'disinterest_reason', 'validated_at',
        ]);

        $statusAnterior = $prospect->status;

        $prospect->status = $dados['status'];
        $prospect->priority = $dados['priority'] ?? $prospect->priority;
        $prospect->next_follow_up_at = $dados['next_follow_up_at'] ?? null;
        $prospect->notes = $dados['notes'] ?? null;
        $prospect->disinterest_reason = $dados['disinterest_reason'] ?? null;

        if ($request->boolean('validated')) {
            $prospect->validated_at ??= now();
        } else {
            $prospect->validated_at = null;
        }

        $prospect->save();

        if (($dados['result'] ?? null) !== null || $statusAnterior !== $prospect->status) {
            $prospect->interactions()->create([
                'admin_user_id' => $request->user()?->getKey(),
                'channel' => $dados['channel'] ?? 'whatsapp',
                'status_before' => $statusAnterior,
                'status_after' => $prospect->status,
                'result' => $dados['result'] ?? null,
                'notes' => $dados['notes'] ?? null,
                'interacted_at' => now(),
            ]);
        }

        PlatformLogger::log('prospeccao.oportunidade_atualizada', 'prospect', $prospect->id, [
            'antes' => $antes,
            'depois' => $prospect->fresh()->only(array_keys($antes)),
        ]);

        return redirect()
            ->route('painel.prospeccao.show', $prospect)
            ->with('success', 'Oportunidade atualizada.');
    }

    private function substituirPlaceholders(\App\Models\Central\Prospect $prospect, string $mensagem, ?string $templateBody): string
    {
        $final = $mensagem;

        if ($templateBody) {
            $final = $templateBody;
        }

        $final = str_replace('{nome}', $prospect->name ?? '', $final);
        $final = str_replace('{categoria}', $prospect->category ?? '', $final);
        $final = str_replace('{cidade}', $prospect->city ?? '', $final);
        $final = str_replace('{remetente}', 'LJ Automação', $final);

        return $final;
    }

    private function baseQuery(string $busca): Builder
    {
        return Prospect::query()->when($busca !== '', function (Builder $query) use ($busca): void {
            $termo = '%'.$busca.'%';
            $digitos = (string) preg_replace('/\D/', '', $busca);

            $query->where(function (Builder $query) use ($termo, $busca, $digitos): void {
                $query->where('name', 'like', $termo)
                    ->orWhere('name_normalized', 'like', '%'.strtolower($busca).'%')
                    ->orWhere('phone_original', 'like', $termo)
                    ->orWhere('address', 'like', $termo);

                if ($digitos !== '') {
                    $query->orWhere('phone_normalized', 'like', '%'.$digitos.'%');
                }
            });
        });
    }

    private function telefonesCompartilhados(Collection $pagina): Collection
    {
        $telefones = $pagina->pluck('phone_normalized')->filter()->unique()->values();

        if ($telefones->isEmpty()) {
            return collect();
        }

        return Prospect::query()
            ->whereIn('phone_normalized', $telefones)
            ->select('phone_normalized')
            ->groupBy('phone_normalized')
            ->havingRaw('count(*) > 1')
            ->pluck('phone_normalized');
    }
}
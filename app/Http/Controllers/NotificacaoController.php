<?php

namespace App\Http\Controllers;

use App\Models\Central\NotificationPreference;
use App\Models\Central\User;
use App\Models\Notification;
use App\Models\NotificationRead;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Central de notificações: sino, histórico e preferências.
 *
 * A listagem é filtrada pela permissão do usuário. O alerta é da clínica, mas
 * quem não tem acesso ao módulo não deve nem saber que a conta está vencida —
 * o filtro é aplicado no servidor, e não escondido no HTML.
 */
class NotificacaoController extends Controller
{
    private const LIMITE_SINO = 12;

    public function index(Request $request): View
    {
        $user = $this->usuario();

        $notificacoes = $this->consultaBase($user)
            ->when($request->filled('categoria'), fn (Builder $query) => $query->where('categoria', $request->string('categoria')->toString()))
            ->when($request->filled('prioridade'), fn (Builder $query) => $query->where('prioridade', $request->string('prioridade')->toString()))
            ->when($request->input('status', 'pendentes') === 'pendentes', fn (Builder $query) => $query->ativas())
            ->when($request->input('status') === 'resolvidas', fn (Builder $query) => $query->whereNotNull('resolved_at'))
            ->with(['leituras' => fn (Builder $query) => $query->where('user_id', $user->id)])
            ->orderByDesc('created_at')
            ->paginate(25)
            ->withQueryString();

        $naoLidas = $this->consultaBase($user)
            ->ativas()
            ->whereDoesntHave('leituras', fn (Builder $query) => $query->where('user_id', $user->id))
            ->count();

        return view('notificacoes.index', [
            'notificacoes' => $notificacoes,
            'naoLidas' => $naoLidas,
            'categorias' => Notification::CATEGORIAS,
            'prioridades' => Notification::PRIORIDADES,
            'preferencias' => $this->preferenciasDoUsuario($user),
        ]);
    }

    /**
     * Polling do sino: contador, últimos itens e preferências de som.
     *
     * Devolver as preferências junto evita uma segunda requisição a cada
     * ciclo — o navegador precisa delas para decidir se toca o som.
     */
    public function naoLidas(Request $request): JsonResponse
    {
        $user = $this->usuario();

        $consulta = $this->consultaBase($user)->ativas();

        $naoLidas = (clone $consulta)
            ->whereDoesntHave('leituras', fn (Builder $query) => $query->where('user_id', $user->id))
            ->count();

        $itens = (clone $consulta)
            ->with(['leituras' => fn ($query) => $query->where('user_id', $user->id)])
            ->orderByDesc('created_at')
            ->limit(self::LIMITE_SINO)
            ->get()
            ->map(fn (Notification $notificacao): array => [
                'id' => $notificacao->id,
                'categoria' => $notificacao->categoria,
                'prioridade' => $notificacao->prioridade,
                'titulo' => $notificacao->titulo,
                'mensagem' => $notificacao->mensagem,
                'url' => $notificacao->url,
                'icone' => $notificacao->icone(),
                'rotulo_prioridade' => $notificacao->rotuloPrioridade(),
                'lida' => $notificacao->leituras->isNotEmpty(),
                'criada_em' => $notificacao->created_at?->toIso8601String(),
                'criada_em_texto' => $notificacao->created_at?->diffForHumans(),
            ])
            ->values();

        return response()->json([
            'nao_lidas' => $naoLidas,
            'itens' => $itens,
            'preferencias' => $this->preferenciasDoUsuario($user),
        ]);
    }

    public function ler(Request $request, Notification $notificacao): JsonResponse|RedirectResponse
    {
        $user = $this->usuario();

        abort_unless(
            $this->consultaBase($user)->whereKey($notificacao->id)->exists(),
            403,
        );

        NotificationRead::query()->updateOrCreate(
            ['notification_id' => $notificacao->id, 'user_id' => $user->id],
            ['read_at' => now()],
        );

        return response()->json(['ok' => true]);
    }

    public function lerTodas(Request $request): JsonResponse|RedirectResponse
    {
        $user = $this->usuario();

        $ids = $this->consultaBase($user)
            ->ativas()
            ->whereDoesntHave('leituras', fn (Builder $query) => $query->where('user_id', $user->id))
            ->pluck('id');

        if ($ids->isNotEmpty()) {
            $agora = now();

            foreach ($ids as $id) {
                NotificationRead::query()->updateOrCreate(
                    ['notification_id' => $id, 'user_id' => $user->id],
                    ['read_at' => $agora],
                );
            }
        }

        return response()->json(['ok' => true, 'marcadas' => $ids->count()]);
    }

    public function preferencias(Request $request): JsonResponse
    {
        return response()->json([
            'preferencias' => $this->preferenciasDoUsuario($this->usuario()),
            'categorias' => Notification::CATEGORIAS,
        ]);
    }

    public function salvarPreferencias(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'sons_ativos' => ['required', 'boolean'],
            'volume' => ['required', 'integer', 'min:0', 'max:100'],
            'categorias' => ['nullable', 'array'],
            'categorias.*' => ['boolean'],
            'navegador_ativo' => ['sometimes', 'boolean'],
        ]);

        $preferencias = NotificationPreference::paraUsuario($this->usuario());

        $preferencias->update([
            'sons_ativos' => (bool) $dados['sons_ativos'],
            'volume' => (int) $dados['volume'],
            'categorias' => $dados['categorias'] ?? $preferencias->categorias,
            'navegador_ativo' => (bool) ($dados['navegador_ativo'] ?? $preferencias->navegador_ativo),
        ]);

        return response()->json([
            'ok' => true,
            'preferencias' => $this->preferenciasDoUsuario($this->usuario()),
        ]);
    }

    private function usuario(): User
    {
        /** @var User $user */
        $user = request()->user();

        return $user;
    }

    private function consultaBase(User $user): Builder
    {
        $query = Notification::query();

        if ($user->isAdmin()) {
            return $query;
        }

        $permissoes = $this->permissoesDoUsuario($user);

        return $query->where(function (Builder $query) use ($permissoes): void {
            $query->whereNull('permissao')->orWhereIn('permissao', $permissoes);
        });
    }

    /**
     * @return list<string>
     */
    private function permissoesDoUsuario(User $user): array
    {
        $user->loadMissing('roles.permissions');

        return $user->roles
            ->flatMap(fn ($role): array => $role->permissions->pluck('name')->all())
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array{sons_ativos: bool, volume: int, categorias: array<string, bool>, navegador_ativo: bool}
     */
    private function preferenciasDoUsuario(User $user): array
    {
        $preferencias = NotificationPreference::paraUsuario($user);

        return [
            'sons_ativos' => $preferencias->sons_ativos,
            'volume' => $preferencias->volume,
            'categorias' => $preferencias->categoriasEfetivas(),
            'navegador_ativo' => $preferencias->navegador_ativo,
        ];
    }
}

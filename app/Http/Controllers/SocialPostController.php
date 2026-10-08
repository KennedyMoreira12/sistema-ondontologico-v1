<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSocialPostDraftRequest;
use App\Models\SocialPost;
use App\Services\AuditLogger;
use App\Services\SocialContentGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SocialPostController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('social.view');

        $posts = SocialPost::query()
            ->when($request->filled('status'), function ($query) use ($request) {
                $query->where('status', $request->string('status'));
            })
            ->when($request->filled('q'), function ($query) use ($request) {
                $termo = $request->string('q')->trim();

                if ($termo !== '') {
                    $query->where(function ($q) use ($termo) {
                        $q->where('titulo', 'like', "%{$termo}%")
                            ->orWhere('tema', 'like', "%{$termo}%");
                    });
                }
            })
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return view('social.index', compact('posts'));
    }

    public function create(): View
    {
        $this->authorize('social.manage');

        return view('social.create');
    }

    /**
     * O post NÃO é criado aqui: o gerador entrega a solicitação ao workflow, que
     * gera o texto e cria o SocialPost pela API interna. Assim o conteúdo nunca
     * nasce sem passar pelo modelo, e um erro de geração não deixa post pela
     * metade no banco da clínica.
     */
    public function store(StoreSocialPostDraftRequest $request, SocialContentGenerator $generator): RedirectResponse
    {
        $this->authorize('social.manage');

        $resultado = $generator->generate($request->validated());

        if (! $resultado['ok']) {
            return redirect()->back()
                ->withInput()
                ->with('error', $resultado['error'] ?? 'Não foi possível gerar o conteúdo.');
        }

        AuditLogger::log('criar', 'social_posts', $resultado['post_id'], depois: ['origem' => 'formulario_web']);

        return redirect()->route('social.show', $resultado['post_id'])
            ->with('success', 'Conteúdo gerado e aguardando aprovação.');
    }

    public function show(SocialPost $socialPost): View
    {
        $this->authorize('social.view');

        return view('social.show', compact('socialPost'));
    }

    public function approve(SocialPost $socialPost): RedirectResponse
    {
        $this->authorize('social.manage');

        if ($socialPost->status !== 'pendente') {
            return redirect()->back()->with('error', 'Somente publicações pendentes podem ser aprovadas.');
        }

        $socialPost->update([
            'status' => 'aprovado',
            'approved_at' => now(),
        ]);

        AuditLogger::log('aprovar', 'social_posts', $socialPost->id, depois: ['status' => 'aprovado']);

        return redirect()->route('social.show', $socialPost)->with('success', 'Publicação aprovada com sucesso.');
    }

    public function reject(Request $request, SocialPost $socialPost): RedirectResponse
    {
        $this->authorize('social.manage');

        $validated = $request->validate([
            'motivo' => ['required', 'string', 'max:500'],
        ]);

        if ($socialPost->status !== 'pendente') {
            return redirect()->back()->with('error', 'Somente publicações pendentes podem ser rejeitadas.');
        }

        $socialPost->update([
            'status' => 'rejeitado',
            'approved_at' => null,
            'published_at' => null,
            'payload' => array_merge($socialPost->payload ?? [], ['rejection_reason' => $validated['motivo']]),
        ]);

        AuditLogger::log('rejeitar', 'social_posts', $socialPost->id, depois: ['status' => 'rejeitado', 'motivo' => $validated['motivo']]);

        return redirect()->route('social.show', $socialPost)->with('success', 'Publicação rejeitada com sucesso.');
    }
}

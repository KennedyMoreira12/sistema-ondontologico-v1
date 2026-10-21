<?php

namespace App\Services\Modulos;

use App\Services\Tenant\VerticalManager;
use App\Support\Modulos\CatalogoModulos;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Decide o que acontece quando alguém abre um MÓDULO do produto.
 *
 * É o único lugar que sabe, para um módulo, entre:
 *
 * - abrir a tela que existe;
 * - mostrar a tela padrão de "funcionalidade planejada";
 * - recusar, porque o módulo não existe para a vertical da clínica.
 *
 * A decisão é sempre no servidor: a vertical vem do registro central da
 * clínica, nunca do navegador.
 */
class ResolutorModulo
{
    public function __construct(
        protected CatalogoModulos $catalogo,
        protected VerticalManager $verticals,
    ) {}

    /**
     * Abre um módulo para a clínica autenticada.
     */
    public function abrir(string $slug): View|RedirectResponse
    {
        // A vertical decide no servidor: um módulo que não existe para a
        // clínica não abre nem por URL digitada.
        if (! $this->verticals->permite($slug)) {
            return redirect()
                ->route('dashboard')
                ->with('warning', 'Este módulo não está disponível para o perfil da sua clínica.');
        }

        $modulo = $this->catalogo->find($slug);

        if ($modulo !== null && $this->catalogo->situacao($slug, $this->verticals->vertical()) === CatalogoModulos::DISPONIVEL) {
            return redirect()->route($modulo['rota']);
        }

        return view('modulos.planejado', [
            'slug' => $slug,
            'titulo' => $this->catalogo->titulo($slug),
            'descricao' => $this->catalogo->descricao($slug),
        ]);
    }
}

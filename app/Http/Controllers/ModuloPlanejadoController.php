<?php

namespace App\Http\Controllers;

use App\Services\Modulos\ResolutorModulo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Tela padrão de um módulo que ainda não está disponível para a clínica.
 *
 * A rota existe para que o menu, o link interno e a URL digitada levem
 * SEMPRE ao mesmo lugar: a tela padrão com a mensagem
 * "Funcionalidade planejada — ainda não disponível para uso."
 */
class ModuloPlanejadoController extends Controller
{
    public function __construct(protected ResolutorModulo $modulos) {}

    public function __invoke(Request $request, string $modulo): View|RedirectResponse
    {
        return $this->modulos->abrir($modulo);
    }
}

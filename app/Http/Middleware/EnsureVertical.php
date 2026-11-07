<?php

namespace App\Http\Middleware;

use App\Services\Tenant\VerticalManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Impede que uma clínica abra um módulo que não existe para a sua vertical.
 *
 * A vertical mora no registro central da clínica e é decidida no servidor: o
 * navegador não escolhe, e não existe parâmetro de URL que troque o módulo
 * liberado. A clínica odontológica não enxerga avaliação de fisioterapia, e a
 * de fisioterapia não enxerga odontograma — mesmo que o usuário monte a URL na
 * mão ou o item do menu esteja no HTML.
 *
 * Roda DEPOIS do ResolveTenant, que é quem define qual clínica está atendendo
 * a requisição.
 */
class EnsureVertical
{
    public function __construct(protected VerticalManager $verticals) {}

    public function handle(Request $request, Closure $next, string $modulo): Response
    {
        if (! $this->verticals->permite($modulo)) {
            abort(403, 'Este módulo não está disponível para o perfil da sua clínica.');
        }

        return $next($request);
    }
}

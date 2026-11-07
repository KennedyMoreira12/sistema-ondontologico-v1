<?php

namespace App\Http\Controllers;

use App\Services\Pacientes\PacienteNomeService;
use Illuminate\View\View;

/**
 * Revisão de pacientes com nomes iguais ou muito parecidos.
 *
 * Nenhum cadastro é fundido aqui. A tela existe para mostrar os grupos e
 * deixar a decisão para a cliente, porque dois "MARIA SILVA" podem ser duas
 * pessoas. Fundir cadastros automaticamente juntaria históricos clínicos e
 * financeiros de pacientes diferentes.
 */
class PacienteDuplicataController extends Controller
{
    public function index(PacienteNomeService $nomes): View
    {
        $this->authorize('pacientes.view');

        $grupos = $nomes->possiveisDuplicatas();

        return view('financeiro.duplicatas.index', [
            'exatos' => $grupos['exatos'],
            'semelhantes' => $grupos['semelhantes'],
        ]);
    }
}

<?php

namespace App\Http\Controllers\Platform\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Sessão do painel da empresa (guard `admin`).
 *
 * Só a saída mora aqui. A entrada é Auth\AuthenticatedSessionController: a tela
 * é única e o guard é escolhido pela credencial, mas a sessão do admin continua
 * separada da sessão de clínica — quem sai do painel derruba só a dele.
 */
class LoginController extends Controller
{
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('admin')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}

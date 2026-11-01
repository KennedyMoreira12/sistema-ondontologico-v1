<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClinicProfile;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Configurações da clínica em atendimento.
 *
 * Não há route model binding aqui de propósito: a clínica é a do tenant
 * ativo, resolvida pelo ResolveTenant a partir de `users.clinic_id`. Aceitar
 * um id pela URL deixaria qualquer usuário logado editar os dados de outra
 * clínica.
 */
class ClinicaController extends Controller
{
    public function edit(): View
    {
        $this->authorize('admin.update');

        return view('admin.clinica.edit', [
            'perfil' => ClinicProfile::current(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $this->authorize('admin.update');

        $validated = $request->validate([
            'legal_name' => ['nullable', 'string', 'max:255'],
            'document' => ['nullable', 'string', 'max:30'],
            'phone' => ['nullable', 'string', 'max:20'],
            'whatsapp' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:191'],
            'postal_code' => ['nullable', 'string', 'max:10'],
            'address' => ['nullable', 'string', 'max:255'],
            'number' => ['nullable', 'string', 'max:20'],
            'complement' => ['nullable', 'string', 'max:255'],
            'district' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'state' => ['nullable', 'string', 'max:2'],
            'state_registration' => ['nullable', 'string', 'max:30'],
            'municipal_registration' => ['nullable', 'string', 'max:30'],
            'cnae' => ['nullable', 'string', 'max:10'],
            'city_ibge_code' => ['nullable', 'string', 'size:7'],
            'tax_regime' => ['nullable', 'in:simples,simples_nacional,lucro_presumido,lucro_real,mei'],
        ]);

        $perfil = ClinicProfile::current();
        $antes = $perfil->only(array_keys($validated));
        $perfil->update($validated);

        AuditLogger::log('atualizar', 'clinic_profile', $perfil->id, antes: $antes, depois: $validated);

        return back()->with('success', 'Dados da clínica atualizados.');
    }
}

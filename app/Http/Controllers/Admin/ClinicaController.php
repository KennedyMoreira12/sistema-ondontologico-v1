<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Clinica;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ClinicaController extends Controller
{
    public function edit(): View
    {
        $this->authorize('admin.update');

        $clinica = Clinica::firstOrCreate(['nome' => 'Clínica']);

        return view('admin.clinica.edit', compact('clinica'));
    }

    public function update(Request $request, Clinica $clinica): RedirectResponse
    {
        $this->authorize('admin.update');

        $validated = $request->validate([
            'nome' => ['required', 'string', 'max:255'],
            'razao_social' => ['nullable', 'string', 'max:255'],
            'documento' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:191'],
            'phone' => ['nullable', 'string', 'max:20'],
            'whatsapp' => ['nullable', 'string', 'max:20'],
            'cep' => ['nullable', 'string', 'max:10'],
            'endereco' => ['nullable', 'string', 'max:255'],
            'numero' => ['nullable', 'string', 'max:20'],
            'complemento' => ['nullable', 'string', 'max:255'],
            'bairro' => ['nullable', 'string', 'max:255'],
            'cidade' => ['nullable', 'string', 'max:255'],
            'estado' => ['nullable', 'string', 'max:2'],
        ]);

        $clinica->update($validated);

        AuditLogger::log('atualizar', 'clinicas', $clinica->id, depois: $validated);

        return back()->with('success', 'Dados da clínica atualizados.');
    }
}
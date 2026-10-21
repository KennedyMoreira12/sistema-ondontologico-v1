<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClinicProfile;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * Configurações da clínica em atendimento.
 *
 * Não há route model binding aqui de propósito: a clínica é a do tenant
 * ativo, resolvida pelo ResolveTenant a partir de `users.clinic_id`. Aceitar
 * um id pela URL deixaria qualquer usuário logado editar os dados de outra
 * clínica.
 *
 * A tela tem duas partes: os dados operacionais (razão social, contato,
 * endereço), que moram no banco da clínica, e a marca (logo e cores), que mora
 * no registro central dela — o mesmo de onde o layout lê o acento e de onde
 * o painel da empresa edita. Uma fonte só para a cor do menu, dos títulos e
 * da impressão.
 */
class ClinicaController extends Controller
{
    public function edit(): View
    {
        $this->authorize('admin.update');

        return view('admin.clinica.edit', [
            'perfil' => ClinicProfile::current(),
            'clinica' => tenant(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $this->authorize('admin.update');

        $dados = $request->validate([
            'legal_name' => ['nullable', 'string', 'max:255'],
            'document' => ['nullable', 'string', 'max:30'],
            'phone' => ['nullable', 'string', 'max:20'],
            'whatsapp' => ['nullable', 'string', 'max:20'],
            'postal_code' => ['nullable', 'string', 'max:10'],
            'address' => ['nullable', 'string', 'max:255'],
            'number' => ['nullable', 'string', 'max:20'],
            'complement' => ['nullable', 'string', 'max:255'],
            'district' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'state' => ['nullable', 'string', 'max:2'],
        ]);

        $perfil = ClinicProfile::current();
        $antes = $perfil->only(array_keys($dados));
        $perfil->update($dados);

        AuditLogger::log('atualizar', 'clinic_profile', $perfil->id, antes: $antes, depois: $dados);

        $this->atualizarMarca($request);

        return back()->with('success', 'Dados da clínica atualizados.');
    }

    /**
     * Marca da clínica: logo e cores.
     *
     * A clínica edita a PRÓPRIA linha central — a dela, não a de outra — e o
     * que ela não configurar continua valendo a cor padrão. Trocar de logo
     * não deixa arquivo antigo no disco: o anterior só é apagado depois que
     * o novo está gravado, para que uma falha no upload não deixe a clínica
     * sem logo nenhuma.
     */
    private function atualizarMarca(Request $request): void
    {
        $clinica = tenant();

        if ($clinica === null) {
            return;
        }

        $marca = $request->validate([
            'primary_color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'secondary_color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
            'remover_logo' => ['nullable', 'boolean'],
        ]);

        $antes = $clinica->only(['logo_path', 'primary_color', 'secondary_color']);
        $novo = [];

        foreach (['primary_color', 'secondary_color'] as $campo) {
            if (! empty($marca[$campo])) {
                $novo[$campo] = $marca[$campo];
            }
        }

        $arquivo = $request->file('logo');

        if ($arquivo !== null) {
            $caminho = $arquivo->store("clinicas/{$clinica->id}/marca", 'public');

            if ($caminho !== false) {
                $novo['logo_path'] = $caminho;
            }
        } elseif ($request->boolean('remover_logo')) {
            $novo['logo_path'] = null;
        }

        if ($novo === []) {
            return;
        }

        $clinica->update($novo);

        $logoAntigo = $antes['logo_path'];

        /**
         * `array_key_exists` e não `??`: o logo removido é gravado como null,
         * e `null ?? $antigo` devolveria o antigo — o arquivo apagado nunca
         * sairia do disco.
         */
        $logoNovo = array_key_exists('logo_path', $novo) ? $novo['logo_path'] : $logoAntigo;

        if ($logoAntigo !== null && $logoNovo !== $logoAntigo) {
            Storage::disk('public')->delete($logoAntigo);
        }

        AuditLogger::log(
            'atualizar_marca',
            'clinic',
            $clinica->id,
            antes: $antes,
            depois: $clinica->fresh()->only(array_keys($antes)),
        );
    }
}

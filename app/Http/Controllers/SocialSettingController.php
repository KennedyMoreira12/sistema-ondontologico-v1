<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateSocialSettingRequest;
use App\Models\SocialSetting;
use App\Services\AuditLogger;
use App\Services\Social\SocialPublisher;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Configuração das contas de Instagram e Facebook DA CLÍNICA.
 *
 * Fica no banco da clínica e só aparece para quem tem `social.manage`. Salvar em
 * branco mantém o token atual: o formulário nunca devolve o valor cifrado, então
 * apagá-lo ao salvar sem digitar seria fácil demais.
 */
class SocialSettingController extends Controller
{
    public function __construct(private SocialPublisher $publisher) {}

    public function edit(): View
    {
        $this->authorize('social.manage');

        return view('social.configuracao', [
            'setting' => SocialSetting::current(),
        ]);
    }

    public function update(UpdateSocialSettingRequest $request): RedirectResponse
    {
        $this->authorize('social.manage');

        $dados = $request->validated();

        $setting = SocialSetting::current() ?? new SocialSetting();
        $token = $dados['page_access_token'] ?? null;

        $setting->facebook_page_id = $dados['facebook_page_id'] ?? null;
        $setting->instagram_business_id = $dados['instagram_business_id'] ?? null;
        $setting->graph_version = $dados['graph_version'] ?? null;

        if (filled($token)) {
            $setting->page_access_token = $token;
        }

        $setting->save();

        AuditLogger::log('configurar_social', 'social_settings', $setting->id, depois: [
            'facebook_page_id' => $setting->facebook_page_id,
            'instagram_business_id' => $setting->instagram_business_id,
            'token_atualizado' => filled($token),
        ]);

        return redirect()
            ->route('social.configuracao')
            ->with('success', 'Configuração salva.');
    }

    public function verificar(): RedirectResponse
    {
        $this->authorize('social.manage');

        $setting = SocialSetting::current();

        if ($setting === null) {
            return back()->with('error', 'Salve a configuração antes de verificar.');
        }

        $resultado = $this->publisher->verificarConexao($setting);

        if (! $resultado['ok']) {
            $setting->update(['last_error' => $resultado['erro']]);

            return back()->with('error', $resultado['erro']);
        }

        $setting->update([
            'facebook_page_name' => $resultado['facebook_page_name'],
            'instagram_business_id' => $resultado['instagram_business_id'] ?: $setting->instagram_business_id,
            'instagram_username' => $resultado['instagram_username'],
            'verified_at' => now(),
            'last_error' => null,
        ]);

        AuditLogger::log('verificar_social', 'social_settings', $setting->id);

        return back()->with('success', 'Conexão verificada: '.($setting->facebook_page_name ?: 'Página da Meta').'.');
    }
}

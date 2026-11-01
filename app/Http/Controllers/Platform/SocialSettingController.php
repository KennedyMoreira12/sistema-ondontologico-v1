<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\UpdatePlatformSocialSettingRequest;
use App\Models\Central\PlatformSocialSetting;
use App\Services\Platform\PlatformLogger;
use App\Services\Platform\Social\SocialPublisher;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Configuração das contas de Instagram e Facebook DA PLATAFORMA (a LJ).
 *
 * Fica no banco central e é exclusiva do super admin. Salvar em branco mantém o
 * token atual: o formulário nunca devolve o valor cifrado, então apagá-lo ao
 * salvar sem digitar seria fácil demais.
 */
class SocialSettingController extends Controller
{
    public function __construct(private SocialPublisher $publisher) {}

    public function edit(): View
    {
        return view('platform.social.configuracao', [
            'setting' => PlatformSocialSetting::current(),
        ]);
    }

    public function update(UpdatePlatformSocialSettingRequest $request): RedirectResponse
    {
        $dados = $request->validated();

        $setting = PlatformSocialSetting::current() ?? new PlatformSocialSetting();
        $token = $dados['page_access_token'] ?? null;

        $setting->facebook_page_id = $dados['facebook_page_id'] ?? null;
        $setting->instagram_business_id = $dados['instagram_business_id'] ?? null;
        $setting->graph_version = $dados['graph_version'] ?? null;

        if (filled($token)) {
            $setting->page_access_token = $token;
        }

        $setting->save();

        PlatformLogger::log('social.configuracao_atualizada', 'platform_social_setting', $setting->id, [
            'facebook_page_id' => $setting->facebook_page_id,
            'instagram_business_id' => $setting->instagram_business_id,
            'token_atualizado' => filled($token),
        ]);

        return redirect()
            ->route('painel.social.configuracao')
            ->with('success', 'Configuração salva.');
    }

    public function verificar(): RedirectResponse
    {
        $setting = PlatformSocialSetting::current();

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

        PlatformLogger::log('social.conexao_verificada', 'platform_social_setting', $setting->id);

        return back()->with('success', 'Conexão verificada: '.($setting->facebook_page_name ?: 'Página da Meta').'.');
    }
}

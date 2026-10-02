<?php

namespace App\Http\Requests\Auth;

use App\Models\Central\AdminUser;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Autenticação do formulário único de login.
 *
 * A tela é uma só, mas as sessões continuam separadas: o guard `admin`
 * (equipe da plataforma) é tentado antes do `web` (usuário de clínica). Os
 * guards NÃO podem ser trocados por um só — `admin_users` e `users` são
 * tabelas diferentes, em bancos diferentes, e a separação é o que impede uma
 * conta de clínica de virar conta de plataforma.
 *
 * Ordem de tentativa: `admin` primeiro porque o painel é a superfície mais
 * privilegiada. Quem tem conta nos dois lados entra pelo painel; para o resto o
 * resultado é idêntico ao de sempre, porque a tentativa no `web` só roda se a
 * do `admin` não casou.
 */
class LoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Attempt to authenticate the request's credentials.
     *
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        $credentials = $this->only('email', 'password');

        $remember = $this->boolean('remember');

        if (Auth::guard('admin')->attempt($credentials, $remember)) {
            $this->ensurePlatformAdminIsActive();
        } elseif (! Auth::guard('web')->attempt($credentials, $remember)) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        RateLimiter::clear($this->throttleKey());
    }

    /**
     * O admin autenticado neste request, ou null se quem entrou é de clínica.
     */
    public function authenticatedPlatformAdmin(): ?AdminUser
    {
        $admin = Auth::guard('admin')->user();

        return $admin instanceof AdminUser ? $admin : null;
    }

    /**
     * Para onde mandar quem acabou de entrar.
     *
     * O `url.intended` é gravado pelo middleware antes de redirecionar o guest
     * para cá, e é ele que faz o admin que tentou abrir `/painel/clinicas`
     * voltar para lá depois de entrar. Mas cada guard tem um espaço de rotas
     * próprio — um admin não tem `users.clinic_id` para o `ResolveTenant`
     * resolver, e um usuário de clínica não passa pelo `auth:admin`. Por isso o
     * `intended` só é honrado quando a URL pertence ao guard que autenticou;
     * caso contrário o padrão do próprio guard.
     */
    public function destinationAfterAuthentication(): string
    {
        $default = $this->authenticatedPlatformAdmin() instanceof AdminUser
            ? route('painel.dashboard')
            : route('dashboard');

        $intended = $this->session()->pull('url.intended');

        if (! is_string($intended)) {
            return $default;
        }

        return $this->belongsToAuthenticatedGuard($intended) ? $intended : $default;
    }

    /**
     * Ensure the login request is not rate limited.
     *
     * @throws ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Get the rate limiting throttle key for the request.
     */
    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('email')).'|'.$this->ip());
    }

    /**
     * A URL guardada pertence ao guard que acabou de autenticar?
     *
     * Sem esta checagem o login vira um laço: um admin que caiu aqui tentando
     * abrir `/agenda` seria devolvido a `/agenda`, rejeitado pelo `auth` (ele
     * não tem clínica), e jogado de volta para o login — indefinidamente.
     */
    private function belongsToAuthenticatedGuard(string $url): bool
    {
        $path = parse_url($url, PHP_URL_PATH);

        if (! is_string($path)) {
            return false;
        }

        $belongsToPanel = $path === '/painel' || str_starts_with($path, '/painel/');

        return $this->authenticatedPlatformAdmin() instanceof AdminUser
            ? $belongsToPanel
            : ! $belongsToPanel;
    }

    /**
     * Senha certa de quem foi desligado continua sendo senha errada: o `attempt`
     * acima só prova a credencial. Sem esta checagem, um ex-membro da equipe que
     * decorou a senha entra no painel de clientes.
     *
     * Não há queda para o guard `web` aqui: se a credencial casou com uma conta
     * de plataforma, o acesso é negado mesmo que exista uma conta de clínica com
     * o mesmo e-mail.
     *
     * @throws ValidationException
     */
    private function ensurePlatformAdminIsActive(): void
    {
        $admin = $this->authenticatedPlatformAdmin();

        if ($admin instanceof AdminUser && $admin->is_active) {
            return;
        }

        Auth::guard('admin')->logout();

        RateLimiter::hit($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => 'Este acesso foi desativado. Fale com o responsável pela plataforma.',
        ]);
    }
}

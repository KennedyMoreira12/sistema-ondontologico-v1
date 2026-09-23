<x-guest-layout>
    <div class="card shadow-sm">
        <div class="card-body p-4">
            <h2 class="h5 mb-3 text-center">Verifique seu e-mail</h2>
            <p class="text-muted small">Obrigado por se cadastrar! Antes de começar, confirme seu e-mail clicando no link que enviamos para você. Não recebeu? Enviaremos outro.</p>

            @if (session('status') == 'verification-link-sent')
                <div class="alert alert-info">Um novo link de verificação foi enviado para o seu e-mail.</div>
            @endif

            <div class="d-flex justify-content-between">
                <form method="POST" action="{{ route('verification.send') }}">
                    @csrf
                    <button type="submit" class="btn btn-outline-primary btn-sm">Reenviar e-mail</button>
                </form>

                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="btn btn-outline-secondary btn-sm">Sair</button>
                </form>
            </div>
        </div>
    </div>
</x-guest-layout>
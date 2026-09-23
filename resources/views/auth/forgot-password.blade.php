<x-guest-layout>
    <div class="card shadow-sm">
        <div class="card-body p-4">
            <h2 class="h5 mb-2 text-center">Recuperar senha</h2>
            <p class="text-muted small text-center mb-4">Informe seu e-mail para receber o link de redefinição.</p>

            @if (session('status'))
                <div class="alert alert-info">{{ session('status') }}</div>
            @endif

            <form method="POST" action="{{ route('password.email') }}">
                @csrf

                <div class="mb-3">
                    <label for="email" class="form-label">E-mail</label>
                    <input id="email" type="email" class="form-control @error('email') is-invalid @enderror" name="email" value="{{ old('email') }}" required autofocus>
                    @error('email')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="d-grid">
                    <button type="submit" class="btn btn-primary">Enviar link de redefinição</button>
                </div>
            </form>
        </div>
    </div>
</x-guest-layout>
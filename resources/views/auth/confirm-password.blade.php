<x-guest-layout>
    <div class="card shadow-sm">
        <div class="card-body p-4">
            <h2 class="h5 mb-3 text-center">Área segura</h2>
            <p class="text-muted small text-center mb-4">Confirme sua senha antes de continuar.</p>

            <form method="POST" action="{{ route('password.confirm') }}">
                @csrf

                <div class="mb-3">
                    <label for="password" class="form-label">Senha</label>
                    <input id="password" type="password" class="form-control @error('password') is-invalid @enderror" name="password" required autocomplete="current-password">
                    @error('password')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="d-grid">
                    <button type="submit" class="btn btn-primary">Confirmar</button>
                </div>
            </form>
        </div>
    </div>
</x-guest-layout>
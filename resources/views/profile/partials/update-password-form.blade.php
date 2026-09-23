<div class="card shadow-sm">
    <div class="card-body">
        <h2 class="h5 mb-1">Atualizar senha</h2>
        <p class="text-muted small mb-3">Use uma senha longa e aleatória para manter sua conta segura.</p>

        <form method="post" action="{{ route('password.update') }}">
            @csrf
            @method('put')

            <div class="mb-3">
                <label for="current_password" class="form-label">Senha atual</label>
                <input type="password" name="current_password" id="current_password" class="form-control @error('current_password', 'updatePassword') is-invalid @enderror" autocomplete="current-password">
                @error('current_password', 'updatePassword')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="mb-3">
                <label for="password" class="form-label">Nova senha</label>
                <input type="password" name="password" id="password" class="form-control @error('password', 'updatePassword') is-invalid @enderror" autocomplete="new-password">
                @error('password', 'updatePassword')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="mb-3">
                <label for="password_confirmation" class="form-label">Confirmar senha</label>
                <input type="password" name="password_confirmation" id="password_confirmation" class="form-control" autocomplete="new-password">
                @error('password_confirmation', 'updatePassword')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="d-flex align-items-center gap-3">
                <button type="submit" class="btn btn-primary">Salvar</button>

                @if (session('status') === 'password-updated')
                    <span class="text-success small">Salvo.</span>
                @endif
            </div>
        </form>
    </div>
</div>
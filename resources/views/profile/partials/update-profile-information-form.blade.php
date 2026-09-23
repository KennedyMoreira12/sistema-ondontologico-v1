<div class="card shadow-sm">
    <div class="card-body">
        <h2 class="h5 mb-1">Informações do perfil</h2>
        <p class="text-muted small mb-3">Atualize as informações pessoais da sua conta.</p>

        <form id="send-verification" method="post" action="{{ route('verification.send') }}">
            @csrf
        </form>

        <form method="post" action="{{ route('profile.update') }}">
            @csrf
            @method('patch')

            <div class="mb-3">
                <label for="name" class="form-label">Nome</label>
                <input type="text" name="name" id="name" class="form-control @error('name', 'updateProfileInformation') is-invalid @enderror" value="{{ old('name', $user->name) }}" required autofocus autocomplete="name">
                @error('name', 'updateProfileInformation')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="mb-3">
                <label for="email" class="form-label">E-mail</label>
                <input type="email" name="email" id="email" class="form-control @error('email', 'updateProfileInformation') is-invalid @enderror" value="{{ old('email', $user->email) }}" required autocomplete="username">
                @error('email', 'updateProfileInformation')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="d-flex align-items-center gap-3">
                <button type="submit" class="btn btn-primary">Salvar</button>

                @if (session('status') === 'profile-updated')
                    <span class="text-success small">Salvo.</span>
                @endif
            </div>
        </form>
    </div>
</div>
<x-app-layout>
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h4 mb-0">Meu perfil</h1>
            <p class="text-muted small mb-0">Gerencie suas informações de conta.</p>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-12">
            @include('profile.partials.update-profile-information-form')
        </div>

        <div class="col-12">
            @include('profile.partials.update-password-form')
        </div>

        @if (! auth()->user()->isAdmin())
            <div class="col-12">
                @include('profile.partials.delete-user-form')
            </div>
        @endif
    </div>
</x-app-layout>
<x-app-layout>
    <div class="text-center py-5">
        <div class="display-4 text-muted"><i class="bi bi-tools"></i></div>
        <h1 class="h3 mt-3">{{ ucfirst($modulo) }}</h1>
        <p class="text-muted">Este módulo está em desenvolvimento. Estará disponível em uma próxima etapa do projeto.</p>
        <a href="{{ route('dashboard') }}" class="btn btn-primary">
            <i class="bi bi-arrow-left me-2"></i>Voltar ao Dashboard
        </a>
    </div>
</x-app-layout>
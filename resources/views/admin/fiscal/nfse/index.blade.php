@extends('layouts.app')

@section('title', 'Notas fiscais - NFS-e')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h4 mb-0">Notas fiscais (NFS-e)</h1>
            <p class="text-muted small mb-0">Rascunhos e notas transmitidas da clínica. A transmissão real permanece bloqueada até autorização.</p>
        </div>
    </div>

    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
        </div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <strong>Houve um problema:</strong>
            <ul class="mb-0 mt-2 small">
                @foreach ($errors->all() as $erro)
                    <li>{{ $erro }}</li>
                @endforeach
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
        </div>
    @endif

    <div class="card shadow-sm" style="max-width: 900px;">
        <div class="card-header bg-white fw-semibold d-flex justify-content-between align-items-center">
            <span><i class="bi bi-file-earmark-text me-2"></i>Lista de notas</span>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Status</th>
                            <th>Tomador</th>
                            <th>Serviço</th>
                            <th>Valor</th>
                            <th>Competência</th>
                            <th>Nº SendNF</th>
                            <th class="text-end">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($drafts as $nfse)
                            <tr>
                                <td>{{ $nfse->id }}</td>
                                <td><span class="badge bg-{{ $nfse->status === 'authorized' ? 'success' : ($nfse->status === 'rejected' ? 'danger' : ($nfse->status === 'blocked' ? 'warning' : 'secondary')) }}">{{ ucfirst($nfse->status ?? 'draft') }}</span></td>
                                <td>{{ $nfse->tomador_nome ?? '-' }}</td>
                                <td class="small">{{ $nfse->servico_descricao ?? '-' }}</td>
                                <td>R$ {{ number_format($nfse->valor_servico ?? 0, 2, ',', '.') }}</td>
                                <td>{{ $nfse->competencia?->format('d/m/Y') ?? '-' }}</td>
                                <td class="small text-muted">{{ $nfse->sendnf_id ?? '-' }}</td>
                                <td class="text-end">
                                    <a href="{{ route('admin.fiscal.nfse.show', $nfse) }}" class="btn btn-sm btn-outline-secondary">Detalhes</a>
                                    @if (filled($nfse->sendnf_id) && $nfse->status === 'authorized')
                                        <a href="{{ route('admin.fiscal.nfse.pdf', $nfse) }}" class="btn btn-sm btn-outline-primary">PDF</a>
                                        <a href="{{ route('admin.fiscal.nfse.xml', $nfse) }}" class="btn btn-sm btn-outline-primary">XML</a>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center text-muted p-3">Nenhuma nota fiscal encontrada.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $drafts->links() }}
        </div>
    </div>
@endsection

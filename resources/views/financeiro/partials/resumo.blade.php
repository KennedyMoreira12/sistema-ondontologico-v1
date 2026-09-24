<div class="row g-3 mb-4">
    <div class="col-12 col-md-6">
        <div class="card border-0 shadow-sm border-start border-4 border-danger h-100">
            <div class="card-body">
                <div class="text-muted small">Total em aberto</div>
                <div class="fs-3 fw-bold text-danger">R$ {{ number_format($totalAberto, 2, ',', '.') }}</div>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-6">
        <div class="card border-0 shadow-sm border-start border-4 border-success h-100">
            <div class="card-body">
                <div class="text-muted small">Contas a receber</div>
                <div class="fs-3 fw-bold">{{ $contas->total() }}</div>
            </div>
        </div>
    </div>
</div>

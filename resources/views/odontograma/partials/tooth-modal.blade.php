<div class="modal fade" id="toothModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="" id="toothUpdateForm">
                @csrf
                @method('PUT')
                <input type="hidden" id="toothDente" name="dente">
                <div class="modal-header">
                    <h5 class="modal-title">Dente <span id="toothNumero"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="toothCondicao" class="form-label">Condição</label>
                        <select id="toothCondicao" name="tooth_condition_id" class="form-select" required>
                            <option value="" selected disabled>Escolha a condição...</option>
                            @foreach ($condicoes as $condicao)
                                <option value="{{ $condicao->id }}" data-cor="{{ $condicao->cor }}">{{ $condicao->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-0">
                        <label for="toothObservacao" class="form-label">Observação</label>
                        <textarea id="toothObservacao" name="observacao" rows="2" class="form-control" placeholder="Ex.: cárie oclusal, restauração insatisfatória..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Fechar</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Salvar</button>
                </div>
            </form>

            <form method="POST" action="" id="toothResetForm" class="modal-footer justify-content-center border-top pt-0"
                onsubmit="return confirm('Remover a marcação deste dente (considerar hígido)?')">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-outline-danger w-100">
                    <i class="bi bi-eraser me-1"></i>Limpar (dente hígido)
                </button>
            </form>
        </div>
    </div>
</div>
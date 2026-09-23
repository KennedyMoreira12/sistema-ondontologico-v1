<div class="card shadow-sm border-danger">
    <div class="card-body">
        <h2 class="h5 mb-1 text-danger">Excluir conta</h2>
        <p class="text-muted small mb-3">Uma vez excluída, todos os seus dados serão removidos permanentemente.</p>

        <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#confirmUserDeletionModal">
            Excluir conta
        </button>

        <div class="modal fade" id="confirmUserDeletionModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <form method="post" action="{{ route('profile.destroy') }}" class="modal-content">
                    @csrf
                    @method('delete')

                    <div class="modal-header">
                        <h5 class="modal-title">Tem certeza que deseja excluir sua conta?</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                    </div>

                    <div class="modal-body">
                        <p class="text-muted small">Para confirmar, digite sua senha abaixo.</p>
                        <input type="password" name="password" id="password" class="form-control" placeholder="Senha" autocomplete="current-password">
                        @error('password', 'userDeletion')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-danger">Excluir conta</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
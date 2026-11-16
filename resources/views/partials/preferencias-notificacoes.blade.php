<!-- Modal de preferências de notificações (PARTE 8). -->
<div class="modal fade" id="scoPreferenciasNotificacoes" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Preferências de notificações</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <form id="scoFormPreferenciasNotificacoes">
                    <div class="mb-3">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="scoSonsAtivos"
                                name="sons_ativos" {{ old('sons_ativos', $preferencias['sons_ativos'] ?? true) ? 'checked' : '' }}>
                            <label class="form-check-label" for="scoSonsAtivos">Ativar sons das notificações</label>
                        </div>
                        <div class="form-text">Quando desligado, nenhum som é tocado, mesmo com alertas urgentes.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small" for="scoVolume">Volume</label>
                        <input type="range" class="form-range" id="scoVolume" name="volume" min="0" max="100"
                            value="{{ old('volume', $preferencias['volume'] ?? 60) }}">
                        <div class="d-flex justify-content-between small text-muted">
                            <span>0%</span>
                            <span id="scoVolumeValor">{{ old('volume', $preferencias['volume'] ?? 60) }}%</span>
                            <span>100%</span>
                        </div>
                    </div>

                    <div class="mb-3">
                        <span class="form-label small d-block mb-2">Categorias com som</span>
                        @foreach ($preferencias['categorias'] ?? [] as $categoria => $ligada)
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="categorias[{{ $categoria }}]"
                                    value="1" id="scoCat{{ $categoria }}" {{ $ligada ? 'checked' : '' }}>
                                <label class="form-check-label" for="scoCat{{ $categoria }}">{{ ucfirst($categoria) }}</label>
                            </div>
                        @endforeach
                    </div>

                    <div class="mb-3">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="scoNavegadorAtivo"
                                name="navegador_ativo" {{ old('navegador_ativo', $preferencias['navegador_ativo'] ?? false) ? 'checked' : '' }}>
                            <label class="form-check-label" for="scoNavegadorAtivo">Notificações do navegador</label>
                        </div>
                        <div class="form-text">
                            Pede permissão ao navegador. Só aparece com esta página aberta — não é push
                            com a aba fechada.
                        </div>
                    </div>

                    <hr>

                    <div class="d-flex justify-content-between align-items-center">
                        <button type="button" class="btn btn-outline-warning btn-sm" id="scoTestarSom">
                            <i class="bi bi-volume-up me-1"></i>Testar som
                        </button>
                        <div>
                            <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                            <button type="button" class="btn btn-primary btn-sm" id="scoSalvarPreferencias">Salvar</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    (() => {
        const modal = document.getElementById('scoPreferenciasNotificacoes');
        if (! modal) {
            return;
        }

        const campoVolume = document.getElementById('scoVolume');
        const rotuloVolume = document.getElementById('scoVolumeValor');

        campoVolume?.addEventListener('input', () => {
            rotuloVolume.textContent = campoVolume.value + '%';
        });

        const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';

        const coletarCategorias = () => {
            const categorias = {};

            modal.querySelectorAll('input[name^="categorias["]').forEach((caixa) => {
                const encontrado = caixa.name.match(/\[(.*)\]/);
                if (encontrado) {
                    categorias[encontrado[1]] = caixa.checked;
                }
            });

            return categorias;
        };

        document.getElementById('scoTestarSom')?.addEventListener('click', () => {
            const urgente = window.confirm(
                'Tocar som de alerta urgente?\n\nOK = urgente · Cancelar = aviso comum'
            );

            window.scoNotificacoes?.tocar(urgente, Number(campoVolume?.value ?? 60));
        });

        document.getElementById('scoSalvarPreferencias')?.addEventListener('click', async () => {
            const navegadorAtivo = document.getElementById('scoNavegadorAtivo').checked;

            // A permissão do navegador só pode ser pedida por gesto do usuário.
            if (navegadorAtivo && 'Notification' in window && Notification.permission === 'default') {
                try {
                    await Notification.requestPermission();
                } catch (erro) {
                    // Sem permissão: a preferência é salva, mas nada é exibido.
                }
            }

            const dados = {
                sons_ativos: document.getElementById('scoSonsAtivos').checked,
                volume: Number(campoVolume?.value ?? 60),
                categorias: coletarCategorias(),
                navegador_ativo: navegadorAtivo,
            };

            try {
                const resposta = await fetch('{{ route('notificacoes.preferencias.save') }}', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrf(),
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify(dados),
                });

                if (! resposta.ok) {
                    return;
                }

                const corpo = await resposta.json();
                window.scoNotificacoes?.definirPreferencias(corpo.preferencias ?? dados);

                bootstrap.Modal.getInstance(modal)?.hide();
            } catch (erro) {
                // Falha silenciosa: o usuário pode tentar de novo.
            }
        });
    })();
</script>
@endpush

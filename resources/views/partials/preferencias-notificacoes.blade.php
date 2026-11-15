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
                        <label class="form-label small">Volume</label>
                        <input type="range" class="form-range" id="scoVolume" name="volume" min="0" max="100"
                            value="{{ old('volume', $preferencias['volume'] ?? 60) }}">
                        <div class="d-flex justify-content-between small text-muted">
                            <span>0%</span>
                            <span id="scoVolumeValor">{{ old('volume', $preferencias['volume'] ?? 60) }}%</span>
                            <span>100%</span>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small d-block mb-2">Categorias com som</label>
                        @foreach ($preferencias['categorias'] ?? [] as $categoria => $ligada)
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="categorias[{{ $categoria }}]" value="1"
                                    id="scoCat{{ $categoria }}" {{ $ligada ? 'checked' : '' }}>
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
                        <div class="form-text">Exige permissão do navegador. Só funciona com a página aberta.</div>
                    </div>

                    <hr>

                    <div class="text-end">
                        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                        <button type="button" class="btn btn-primary btn-sm" id="scoSalvarPreferencias">Salvar</button>
                        <button type="button" class="btn btn-warning btn-sm ms-2" id="scoTestarSom">Testar som</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    document.getElementById('scoVolume')?.addEventListener('input', (e) => {
        document.getElementById('scoVolumeValor').textContent = e.target.value + '%';
    });

    document.getElementById('scoSalvarPreferencias')?.addEventListener('click', async () => {
        const form = document.getElementById('scoFormPreferenciasNotificacoes');
        const dados = Object.fromEntries(new FormData(form));
        dados.sons_ativos = document.getElementById('scoSonsAtivos').checked;
        dados.navegador_ativo = document.getElementById('scoNavegadorAtivo').checked;

        try {
            const resposta = await fetch('{{ route('notificacoes.preferencias.save') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify(dados),
            });

            if (resposta.ok) {
                window.scoPreferenciasSons = (await resposta.json()).preferencias;
                const modal = bootstrap.Modal.getInstance(document.getElementById('scoPreferenciasNotificacoes'));
                modal?.hide();
            }
        } catch (erro) {
            // Falha silenciosa.
        }
    });

    document.getElementById('scoTestarSom')?.addEventListener('click', () => {
        const urgente = confirm('Tocar som de alerta urgente?\n(Cancelar toca o som de aviso comum.)');
        const evento = new CustomEvent('alerta:novo', {
            detail: {
                id: 'teste_' + Date.now(),
                prioridade: urgente ? 'critical' : 'info',
                categoria: 'financeiro',
                titulo: 'Teste de som',
            },
        });
        document.dispatchEvent(evento);
    });
</script>
@endpush

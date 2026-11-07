<div class="modal fade" id="editModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="" id="editForm">
                @csrf
                @method('PUT')
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-calendar-event me-2"></i>Editar consulta</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body pb-0">
                    <div class="alert alert-light border mb-3 py-2">
                        <strong>Paciente:</strong> <span id="editPacienteNome"></span>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="editPaciente" class="form-label">Paciente *</label>
                            <select id="editPaciente" name="paciente_id" class="form-select" required>
                                @foreach ($pacientes as $paciente)
                                    <option value="{{ $paciente->id }}">{{ $paciente->nome }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="editProfissional" class="form-label">Profissional *</label>
                            <select id="editProfissional" name="profissional_id" class="form-select" required>
                                @foreach ($profissionais as $profissional)
                                    <option value="{{ $profissional->id }}">{{ $profissional->nome }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="editSala" class="form-label">Sala</label>
                            <select id="editSala" name="room_id" class="form-select">
                                <option value="">Sem sala</option>
                                @foreach ($salas as $sala)
                                    <option value="{{ $sala->id }}">{{ $sala->nome }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="editStatus" class="form-label">Status</label>
                            <select id="editStatus" name="appointment_status_id" class="form-select">
                                @foreach ($statuses as $status)
                                    <option value="{{ $status->id }}">{{ $status->label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="editDia" class="form-label">Data *</label>
                            <input type="date" id="editDia" name="dia" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label for="editHora" class="form-label">Hora *</label>
                            <input type="time" id="editHora" name="hora_inicio" class="form-control" step="900" required>
                        </div>
                        <div class="col-md-4">
                            <label for="editDuracao" class="form-label">Duração</label>
                            <select id="editDuracao" name="duracao" class="form-select">
                                @foreach ([15, 30, 45, 60, 90, 120] as $min)
                                    <option value="{{ $min }}">{{ $min }} min</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12">
                            <label for="editTitulo" class="form-label">Título</label>
                            <input type="text" id="editTitulo" name="titulo" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label for="editServico" class="form-label">Serviço</label>
                            <input type="text" id="editServico" name="servico" class="form-control" list="servicosComuns" placeholder="Pilates, laser, massagem...">
                        </div>
                        <div class="col-md-4">
                            <label for="editArea" class="form-label">Área</label>
                            <input type="text" id="editArea" name="area" class="form-control" list="areasTratamento" placeholder="Ex.: AXILA">
                        </div>
                        <div class="col-md-4">
                            <label for="editValor" class="form-label">Valor</label>
                            <input type="number" step="0.01" min="0" id="editValor" name="valor" class="form-control">
                        </div>
                        <div class="col-12">
                            <label for="editObservacao" class="form-label">Observação</label>
                            <textarea id="editObservacao" name="observacao" rows="2" class="form-control"></textarea>
                        </div>
                        <div class="col-12">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="retorno" value="1" id="editRetorno">
                                <label class="form-check-label" for="editRetorno">Consulta de retorno</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer pt-3">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Fechar</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Salvar</button>
                </div>
            </form>

            <form method="POST" action="" id="editCancelForm" class="modal-footer justify-content-center border-top pt-0"
                onsubmit="return confirm('Cancelar esta consulta?')">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-outline-danger w-100">
                    <i class="bi bi-x-circle me-1"></i>Cancelar consulta (mantém histórico)
                </button>
            </form>
        </div>
    </div>
</div>
<div class="modal fade" id="agendarModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <form method="POST" action="{{ route('agenda.store') }}">
            @csrf
            <input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', (string) \Illuminate\Support\Str::uuid()) }}">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-calendar-plus me-2"></i>Novo agendamento</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="paciente_id" class="form-label">Paciente *</label>
                            <select id="paciente_id" name="paciente_id" class="form-select @error('paciente_id') is-invalid @enderror" required>
                                <option value="">Selecione...</option>
                                @foreach ($pacientes as $paciente)
                                    <option value="{{ $paciente->id }}" @selected(old('paciente_id') == $paciente->id)>{{ $paciente->nome }}</option>
                                @endforeach
                            </select>
                            @error('paciente_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label for="profissional_id" class="form-label">Profissional *</label>
                            <select id="profissional_id" name="profissional_id" class="form-select @error('profissional_id') is-invalid @enderror" required>
                                <option value="">Selecione...</option>
                                @foreach ($profissionais as $profissional)
                                    <option value="{{ $profissional->id }}" @selected(old('profissional_id') == $profissional->id)>{{ $profissional->nome }}</option>
                                @endforeach
                            </select>
                            @error('profissional_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label for="room_id" class="form-label">Sala</label>
                            <select id="room_id" name="room_id" class="form-select">
                                <option value="">Sem sala</option>
                                @foreach ($salas as $sala)
                                    <option value="{{ $sala->id }}" @selected(old('room_id') == $sala->id)>{{ $sala->nome }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12">
                            <label for="titulo" class="form-label">Título</label>
                            <input type="text" id="titulo" name="titulo" value="{{ old('titulo') }}" class="form-control" placeholder="Ex.: Consulta inicial, Limpeza...">
                        </div>
                        <div class="col-md-4">
                            <label for="servico" class="form-label">Serviço</label>
                            <input type="text" id="servico" name="servico" value="{{ old('servico') }}" class="form-control" list="servicosComuns" placeholder="Pilates, laser, massagem...">
                        </div>
                        <div class="col-md-4">
                            <label for="area" class="form-label">Área</label>
                            <input type="text" id="area" name="area" value="{{ old('area') }}" class="form-control" list="areasTratamento" placeholder="Ex.: AXILA">
                        </div>
                        <div class="col-md-4">
                            <label for="valor" class="form-label">Valor</label>
                            <input type="number" step="0.01" min="0" id="valor" name="valor" value="{{ old('valor') }}" class="form-control" placeholder="0,00">
                        </div>
                        <datalist id="servicosComuns">
                            @foreach (['Pilates', 'Fisioterapia', 'Massagem', 'Depilação a laser', 'Limpeza de pele', 'Design de sobrancelha'] as $servico)
                                <option value="{{ $servico }}"></option>
                            @endforeach
                        </datalist>
                        <datalist id="areasTratamento">
                            @foreach (['AXILA', 'VIRILHA', 'ABDÔMEN', 'COXA', 'BRAÇO', 'ROSTO', 'COSTAS', 'PERNA'] as $area)
                                <option value="{{ $area }}"></option>
                            @endforeach
                        </datalist>
                        <div class="col-md-4">
                            <label for="dia" class="form-label">Data *</label>
                            <input type="date" id="dia" name="dia" value="{{ old('dia', request('data', today()->toDateString())) }}" class="form-control @error('dia') is-invalid @enderror" required>
                            @error('dia')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-4">
                            <label for="hora_inicio" class="form-label">Hora *</label>
                            <input type="time" id="hora_inicio" name="hora_inicio" value="{{ old('hora_inicio', '09:00') }}" class="form-control @error('hora_inicio') is-invalid @enderror" step="900" required>
                            @error('hora_inicio')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-4">
                            <label for="duracao" class="form-label">Duração</label>
                            <select id="duracao" name="duracao" class="form-select">
                                @foreach ([15, 30, 45, 60, 90, 120] as $min)
                                    <option value="{{ $min }}" @selected(old('duracao', 30) == $min)>{{ $min }} min</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12">
                            <label for="observacao" class="form-label">Observação</label>
                            <textarea id="observacao" name="observacao" rows="2" class="form-control" placeholder="Observações sobre a consulta">{{ old('observacao') }}</textarea>
                        </div>
                        <div class="col-12">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="retorno" value="1" id="retorno" @checked(old('retorno'))>
                                <label class="form-check-label" for="retorno">Consulta de retorno</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Agendar</button>
                </div>
            </div>
        </form>
    </div>
</div>
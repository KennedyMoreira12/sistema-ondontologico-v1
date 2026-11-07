# ETAPA 2 — Agenda e Horários Recorrentes

## 2.1 Objetivo
Substituir a grade semanal sem data da `AGENDA CLINICA .xlsx` pela estrutura de agendamento por data do LJ SmartClinic, permitindo que a cliente visualize e crie agendamentos com datas reais (second-day scheduling), mantendo a compatibilidade com a lógica de horários da planilha (SEGUNDA a SÁBADO, frações de hora).

## 2.2 Diagnóstico da Planilha (já auditado)

| Característica | Planilha AGENDA CLINICA .xlsx |
|----------------|------------------------------|
| Total de abas | 31 abas com diferentes finalidades |
| Formato grade | SEGUNDA a SÁBADO, horários em frações (0.25 = 6h, 0.333333 = 8h, etc.) |
| Horários exemplos | L4: B=0.25 | I=KAROLYNE, J=0.3333333333333333; L5: I=ANA KAROLINE, J=0.375 |
| Abas relacionadas | AGENDA ANTIGA (51 linhas), CALENDÁRIO 2026, PILOTES, CALENDÁRIO PILATES, MASSAGEM, CAIXA MARÇO a DEZEMBRO MASSAGEM |
| Observação crítica | "AGENDA CLINICA .xlsx não foi importada. É grade SEGUNDA..SÁBADO sem data — importar exigiria inventar datas. Recomendo recadastrar os horários futuros direto na agenda." |

## 2.3 O Que o LJ SmartClinic Já Tem

| Funcionalidade | Componente | Status |
|----------------|------------|--------|
| Agendamento por data | `AgendaController@store`, rota `agendas.store` | ⚡ Já existe |
| Visualização calendar | `DashboardController@index` + Livewire/Blade | ⚡ Já existe |
| Lista de horários por profissional | `ProfessionalSchedule` model | ⚡ Já existe |
| Filtro por data | Rotas `agendas.index` com filtros por date | ⚡ Já existe |
| Conflito de horário | Validado no controller/model | ⚡ Parcial (precisa de ajuste) |

## 2.3 O Que Precisa Ser Implementado

### 2.3.1 Redesenho da Tela de Agenda

A grade semanal atual (que exibe SEGUNDA..SÁBADO sem datas) deve ser substituída por:

1. **Visualização por dia** — O usuário vê os horários disponíveis para um dia específico
2. **Visualização semanal** — Lista dos agendamentos da semana, por dia
3. **Criação de agendamento** — Formulário que pede a data específica + horário + profissional + serviço

### 2.3.2 Mapeamento de Horários da Planilha para o Sistema

A planilha usa frações de dia (0 a 1). Precisamos mapear:

| Fração da Planilha | Horário Equivalente | Observação |
|--------------------|--------------------|------------|
| 0.25 | 6:00 (meio-dia) | 6 horas = 0.25 * 24 |
| 0.3333333333333333 | 8:00 | 8 horas |
| 0.5 | 12:00 (meio-dia) | 12 horas |
| 0.5833333333333334 | 14:00 (2pm) | 14/24 = 0.5833 |
| 0.375 | 9:00 | 9 horas |
| 0.3125 | 7:30 | 7.5 horas |
| 0.2916666666666667 | 7:00 | 7 horas (0.291666... = 7/24) |

**Implementação:** Converter fração → hora no controller/view ao exibir horários; converter hora → fração ao armazenar (ou armazenar em formato HH:MM nativo do Laravel).

### 2.3.3 Novo Fluxo de Agendamento

```
Fluxo antigo (planilha):      Fluxo novo (LJ SmartClinic):
Grade SEGUNDA..SÁBADO        Calendar diário + agendamento por data
                              |
                              +--- Usuário seleciona data
                              |
                              +--- Formulário: data + horário + profissional + serviço
                              |
                              +--- Validar conflito
                              |
                              +--- Criar appointment com data REAL
```

### 2.3.3 Componentes Técnicos para Implementação

#### 2.3.3.1 Rotas Adicionais (ou ajustes)

Rotas já existentes precisam de ajustes:
- `agendas.index` — listar agendamentos por período
- `agendas.create` — formulário de novo agendamento
- `agendas.store` — salvar novo agendamento
- `agendas.edit` — editar agendamento existente
- `agendas.destroy` — cancelar agendamento

Possíveis novas rotas:
- `agendas.weekly` — view semanal formatada
- `professors/{id}/schedule` — horário do profissional

#### 2.3.3.2 Controller: AgendaController

Ajustes necessários em `app/Http/Controllers/AgendaController.php`:

```php
public function index(Request $request)
{
    $query = Appointment::where('clinic_id', clinicId());
    
    // Filtros por período
    if ($request->has('start_date')) {
        $query->where('date', '>=', $request->start_date);
    }
    if ($request->has('end_date')) {
        $query->where('date', '<=', $request->end_date);
    }
    if ($request->has('professional_id')) {
        $query->where('professional_id', $request->professional_id);
    }
    if ($request->has('status')) {
        $query->where('status', $request->status);
    }
    
    $appointments = $query->orderBy('date', 'asc')
                          ->orderBy('start_time', 'asc')
                          ->paginate(20);
    
    $professionals = Professional::where('clinic_id', clinicId())
        ->active()
        ->get();
    
    return view('agenda.index', compact('appointments', 'professionals'));
}

public function store(Request $request)
{
    $validated = $request->validate([
        'professional_id' => 'required|exists:professionals,id',
        'service' => 'required|string|max:100',
        'date' => 'required|date|after_or_equal:today',
        'start_time' => 'required|date_format:H:i',
        'end_time' => 'nullable|date_format:H:i|after:start_time',
    ]);
    
    // Converter horário para formato interno
    $startDateTime = Carbon::parse($request->date . ' ' . $request->start_time);
    $endDateTime = $request->end_time 
        ? Carbon::parse($request->date . ' ' . $request->end_time) 
        : null;
    
    // Verificar conflito
    $conflito = Appointment::where('professional_id', $request->professional_id)
        ->where('clinic_id', clinicId())
        ->where(function ($q) use ($startDateTime, $endDateTime) {
            $q->where('date', $request->date)
              ->where(function ($q2) use ($startDateTime, $endDateTime) {
                  $q2->where('start_time', '<=', $request->start_time)
                     ->where('end_time', '>=', $request->start_time);
              });
        })
        ->exists();
    
    if ($conflito) {
        return back()
            ->with('error', 'Conflito de horário: profissional já está disponível neste horário.')
            ->with('errors', true);
    }
    
    $appointment = Appointment::create([
        'clinic_id' => clinicId(),
        'professional_id' => $request->professional_id,
        'service' => $request->service,
        'date' => $request->date,
        'start_time' => $startDateTime,
        'end_time' => $endDateTime,
        'status' => 'agendado',
    ]);
    
    return redirect()
        ->route('agendas.index')
        ->with('success', 'Agendamento criado com sucesso.');
}
```

#### 2.3.3.3 View: Nova Tela de Agenda

A view deve substituir a grade semanal por:

1. **Seletor de data** — Calendar picker ou campo de data
2. **Lista de profissionais** — Selecionar profissional
3. **Seleção de horário** - Horários disponíveis (gerados a partir do schedule do profissional)
4. **Seleção de serviço** - Serviços cadastrados
5. **Botão de agendar** - Envia o formulário

Exemplo simplificado da view:

```blade
<x-card title="Agendamento">
    <x-slot name="header">
        <x-icon name="calendar" class="icon" /> Agendamento
    </x-slot>
    
    <form wire:submit.prevent="agendar">
        <x-row>
            <x-col>
                <x-label for="data">{{ __('Data') }}</x-label>
                <x-input type="date" id="data" wire:model="data" required />
            </x-col>
            <x-col>
                <x-label for="profissional">{{ __('Profissional') }}</x-label>
                <x-select id="profissional" wire:model="profissional" required>
                    @foreach($profissionais as $prof)
                        <option value="{{ $prof->id }}">{{ $prof->name }}</option>
                    @endforeach
                </x-select>
            </x-col>
        </x-row>
        
        <x-row>
            <x-col>
                <x-label for="horario">{{ __('Horário') }}</x-label>
                <x-select id="horario" wire:model="horario" required>
                    <!-- Horários gerados dinamicamente -->
                    @foreach($horariosDisponiveis as $horario)
                        <option value="{{ $horario }}">{{ $horario }}</option>
                    @endforeach}
                </x-select>
            </x-col>
            <x-col>
                <x-label for="servico">{{ __('Serviço') }}</x-label>
                <x-select id="servico" wire:model="servico" required>
                    <option value="Depilação a laser">Depilação a laser</option>
                    <option value="Pilates">Pilates</option>
                    <option value="Massagem">Massagem</option>
                    <option value="Fisioterapia">Fisioterapia</option>
                </x-select>
            </x-col>
        </x-row>
        
        <x-actions>
            <x-button type="primary">{{ __('Agendar') }}</x-button>
        </x-actions>
    </form>
</x-card>
```

#### 2.3.3.4 Geração de Horários Disponíveis

Precisamos gerar a lista de horários disponíveis baseada no schedule do profissional. Isso pode ser feito em um componente Livewire ou endpoint AJAX.

```php
// Endpoint ou método Livewire
public function horariosDisponiveis($profissionalId, $data)
{
    $profissional = Professional::find($profissionalId);
    
    // Horário de trabalho padrão (poderia ser do schedule do profissional)
    $horarioInicio = Carbon::parse('07:00'); // 07:00 = 0.291666... em fração
    $horarioFim = Carbon::parse('19:00'); // 19:00
    
    $horarios = [];
    $horarioAtual = $horarioInicio;
    
    while ($horarioAtual->lessThanOrEqualTo($horarioFim)) {
        // Verificar se já há agendamento neste horário
        $jáTem = Appointment::where('professional_id', $profissionalId)
            ->where('date', $data)
            ->where(function ($q) use ($horarioAtual) {
                $q->where('start_time', '<=', $horarioAtual)
                  ->where('end_time', '>=', $horarioAtual);
            })
            ->exists();
        
        if (!$jáTem) {
            $horarios[] = $horarioAtual->format('H:i');
        }
        
        // Incrementar 30 minutos (ou o intervalo padrão)
        $horarioAtual = $horarioAtual->copy()->addMinutes(30);
    }
    
    return $horarios;
}
```

### 2.3.4 Regra de Conflito de Horário

O sistema deve impedir dois agendamentos simultâneos para o mesmo profissional. A regra da planilha é implícita: cada hora/slot só pode ser usado uma vez por dia.

Lógica de conflito:

```php
// No controller store() ou no model
public function checkConflict($professionalId, $date, $startTime, $endTime): bool
{
    return Appointment::where('professional_id', $professionalId)
        ->where('date', $date)
        ->where(function ($query) use ($startTime, $endTime) {
            $query->where(function ($q) use ($startTime, $endTime) {
                $q->where('start_time', '<=', $startTime)
                  ->where('end_time', '>=', $startTime);
            })
            ->orWhere(function ($q) use ($startTime, $endTime) {
                $q->where('start_time', '<=', $endTime)
                  ->where('end_time', '>=', $endTime);
            })
            ->orWhere(function ($q) use ($startTime, $endTime) {
                $q->where('start_time', '>=', $startTime)
                  ->where('end_time', '<=', $endTime);
            });
        })
        ->exists();
}
```

### 2.3.5 Cancelamento e Reagendamento

O fluxo deve permitir:

1. **Cancelamento** — Marcar appointment status `cancelado`
2. **Reagendamento** — Cancelar um e criar outro em horário diferente
3. **Falta** — Marcar status `faltou` quando cliente não comparece

### 2.4 Entregáveis da Etapa 2

#### 2.4.1 Novas Rotas (ou ajustes)

```php
// routes/web.php (ajustes)
Route::get('/agenda', [AgendaController::class, 'index'])->name('agendas.index');
Route::post('/agenda', [AgendaController::class, 'store'])->name('agendas.store');
Route::get('/agenda/{appointment}/edit', [AgendaController::class, 'edit'])->name('agendas.edit');
Route::put('/agenda/{appointment}', [AgendaController::class, 'update'])->name('agendas.update');
Route::delete('/agenda/{appointment}', [AgendaController::class, 'destroy'])->name('agendas.destroy');
Route::get('/agenda/horarios-disponiveis', [AgendaController::class, 'horariosDisponiveis'])->name('agendas.horarios.disponiveis');
```

#### 2.4.2 Novas Views

- `resources/views/agenda/index.blade.php` — tela de agenda com seletor de data, profissional, horário, serviço
- `resources/views/agenda/horarios-ajax.blade.php` ou endpoint JSON para horários disponíveis

#### 2.4.3 Ajustes no Controller

- `AgendaController@index` — listar com filtros de data
- `AgendaController@store` — criar agendamento com validação de conflito
- `AgendaController@destroy` — cancelar agendamento
- `AgendaController@horariosDisponiveis` — retornar horários livres

#### 2.4.3 Migrações (se necessárias)

Verificar se `appointments` table tem os fields necessários:
- `date` (date) — já deve existir
- `start_time` / `end_time` (time or string) — pode precisar de migration
- `status` (enum) — pode precisar de migration

#### 2.4.4 Documentação

- Atualizar `docs/daiane/MATRIZ_FUNCIONAL_PLANILHAS_SISTEMA.md` com novo mapeamento agenda
- Criar `docs/daiane/AGENDA_IMPLEMENTACAO_NOTAS.md` com notas da implementação

## 2.5 Riscos e Mitigações

| Risco | Gravidade | Mitigação |
|-------|-----------|-----------|
| Usuários resistirem à mudança da grade semanal para agendamento por data | Alta | Transição gradiente: manter view antiga como "modo legado" por enquanto; mostrar benefícios (agendamento por data real, evitando erros de data) |
| Perda da grade visual SEGUNDA..SÁBADO que a cliente conhece | Média | Criar view "Modo Grade Semanal" que liste horários por dia, mesmo que seja nova estrutura; permitir que cliente se adapte no seu ritmo |
| Conflitos de horário não detectados | Média | Testes exhaustivos com horários sobrepostos; validação no banco e no frontend |
| Horários em fração (0.25, 0.3333) não mapeados corretamente | Média | Criar tabela de conversão fracção→hora; permitir que profissional configure seu schedule em horas nativas |
| Agendamentos para datas passadas | Baixa | Validar no controller: `date >= today()`; permitir agendar apenas para o futuro |

## 2.7 Ordem de Execução Recomendada

1. **Dia 1-2:** Auditar current `AgendaController` e `appointments` table structure; adicionar campos se necessários (migration)
2. **Dia 3-4:** Criar novo view `agenda/index.blade.php` com seletor de data/profissional/horário/serviço
3. **Dia 5-6:** Implementar `AgendaController@store` com validação de conflito
4. **Dia 7:** Implementar endpoint `horariosDisponiveis` 
5. **Dia 8:** Testes de agendamento (horários conflitantes, datas inválidas, profissional inexistente)
6. **Dia 9-10:** Testes com a cliente Daiane (se possível) ou teste interno
7. **Dia 11:** Ajustes finais baseados nos testes

## 2.8 Critério de Conclusão da Etapa 2

A Etapa 2 será considerada concluída quando:

- [ ] Nova tela de agenda disponível (view Blade) com seletor de data, profissional, horário, serviço
- [ ] Agendamento por data funcional (criar novo appointment com data REAL)
- [ ] Validação de conflito de horário funcionando (não permitir dois appointments no mesmo tempo para mesmo profissional)
- [ ] Listagem de horários disponíveis por profissional e data
- [ ] Cancelamento de agendamento funcional (alterar status para `cancelado`)
- [ ] Interface mais simples do que grade semanal sem data
- [ ] Testes de regressão passam (pelo menos 80% dos testes de agenda)
- [ ] Isolamento de tenants preservado (clinic_000001 intocado)

## 2.9 Próximos Passos Após a Etapa 2

Após conclusão da Etapa 2, prosseguir para:

- **Etapa 3:** Atendimento, sessões e pacotes (registro de presença/falta, pacotes de sessão, histórico)
- **Etapa 4:** Contas a pagar e receber (tabela específica, migração dos 94 suppliers)
- **Etapa 5:** Relatórios e controles administrativos
- **Etapa 6:** Validação com a cliente (protocolo de 9 tarefas críticas)

---

*Documento de plano da Etapa 2 — Agenda e Horários Recorrentes.*
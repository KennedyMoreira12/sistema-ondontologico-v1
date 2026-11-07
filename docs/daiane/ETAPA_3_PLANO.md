# ETAPA 3 — Atendimento, Sessões e Pacotes

## 3.1 Objetivo
Integrar os fluxos de laser, Pilates, massagem e fisioterapia em uma experiência unificada de atendimento, permitindo cadastro de pacotes, controle de sessões utilizadas/restantes, registro de presença/falta e disponibilização de histórico do cliente. Este é o coração da operação diária da clínica Daiane.

## 3.2 Diagnóstico das Planilhas

### 3.2.1 Depilação a Laser (DEPILAÇÃO LASER.xlsx)

| Característica | Detalhe |
|----------------|---------|
| Total de abas | 95 (um arquivo por dia/de tratamento) |
| Estrutura por aba | Cada aba = um dia/consulta; colunas: HORÁRIO, ÁREA, NOME, PRESENÇA, VALOR A RECEBER, FORMA DE PAGAMENTO, PAGO |
| Fórmulas principais | COUNTIF, SUM entre linhas para totalização de áreas e pagamentos |
| Validações de lista | 'DADOS!$A$5:$A$16', 'DADOS!$E$5:$E$8', 'DADOS!$C$5:$C$7' (áreas/serviços/pagamento) |
| Autofiltro | Em algumas abas (ex: `$B$4:$M$82`) |
| Campos críticos | ÁREA (AXILA, VIRILHA, ABDOMEN, etc.), PRESENCE (PRESENTE/AUSENTE), VALOR, FORMA PGTO |
| Exemplos de fórmulas | `f(J25) = SUM(J2:J24)`, `f(D69) = COUNTIF(D5:D60,C69)`, `f(E69) = COUNTIF(E5:E60,C69)` |

**Tradução para o LJ SmartClinic:**
- `appointments` table — já tem fields: area, status (atendido/faltou), valor, payment_method
- Necessário aprimorar validação de área do tratamento
- Registrar presença/falta já flui via status do appointment
- Fórmulas COUNTIF/SUM podem ser substituídas por relatórios aggregates no sistema

### 3.2.2 Pilates (PGTO PILATES.xlsx)

| Característica | Detalhe |
|----------------|---------|
| Total de abas | 2 abas + 1 aba de campanha (Outubro Rosa) |
| PAGAMENTOS PILATES 2026 | 107 linhas, 10 fórmulas; tabela cruzada (clientes × meses: MARÇO a OUTUBRO) |
| Campos | L1: A= | B=CLIENTES | C=MARÇO | D=ABRIL | ... | J=OUTUBRO | L=PRESENÇA |
| Registros | L2: A=1.0 | B=ANGELO | F=200.0 | G=0 | H=200.0 | I=200.0 | J=200.0 | L=PRESENTE |
| L3: B=ANA PAULA RODRIGUES | J=100.0 |
| L4: B=ANA JULIA SALES | J=100.0 |
| LISTA OUTUBRO ROSA | 1 linha apenas, controle esparcido de campanha |

**Tradução para o LJ SmartClinic:**
- `pacotes_sessao` table — 369 pacotes já no `clinic_000005`
- `consumo_sessao` table — controle de sessões consumidas
- `appointments` — já registra atendimentos
- Tabela cruzada client×mês planilha não mapeia 1:1 com `consumo_sessao`; necessário reformatar dados

### 3.2.3 Massagem (AGENDA CLINICA + PAGAMENTOS.xlsx)

| Característica | Detalhe |
|----------------|---------|
| Controle de caixa | Abas CAIXA MARÇO a DEZEMBRO MASSAGEM na AGENDA CLINICA .xlsx |
| Serviço | Frequente em PAGAMENTOS.xlsx (serviço "MASSAGEM") |
| Formato | Valores soltos por consulta, soma manual mensal |
| Integração | Já lançados como `payments` com serviço "MASSAGEM" |

### 3.2.4 Fisioterapia (PAGAMENTOS.xlsx)

| Característica | Detalhe |
|----------------|---------|
| Registro em 69 abas (meses 2022-2026) |  |
| Serviços | FISIOTERAPIA, FISIOTERAPIA DOMICILIAR, LIMPEZA DE PELE, DESIGN DE SOBRANCELHA, etc. |
| Tipos de PGTO | PIX, DINHEIRO, CARTÃO DE CRÉDITO, etc. |
| Alguns registros têm plano de saúde | Campo K com valores (copagamento) |

## 3.3 O Que o LJ SmartClinic Já Tem

| Funcionalidade | Componente | Status |
|----------------|------------|--------|
| Cadastro de pacientes | `pacientes` table (767 no clinic_000005) | ⚡ Já existe |
| Registro de consulta/atendimento | `appointments` table (1.362 no clinic_000005) | ⚡ Já existe |
| Status de presença/falta | `appointments.status` (atendido/faltou) | ⚡ Já parcialmente implementado (48 correções) |
| Pacotes de sessão | `pacotes_sessao` table (369 no clinic_000005) + `consumo_sessao` | ⚡ Já existe |
| Registro de consumo | `consumo_sessao` table | ⚡ Já existe |
| Avaliações do atendimento | `avaliacoes` table | ⚡ Já existe |
| Histórico do cliente | `appointments` + `evolucoes_sessao` + `avaliacoes` | ⚡ Já existe, mas consultabilidade pode ser melhorada |

## 3.3 O Que Precisa Ser Implementado

### 3.3.1 Melhoria no Controle de Presença e Falta

| Ação da Planilha | LJ SmartClinic | Implementação |
|------------------|----------------|---------------|
| Marcar PRESENTE | `appointments.status = 'atendido'` | Já existe; confirmar que fluxo está visível na interface |
| Marcar AUSENTE | `appointments.status = 'faltou'` | Já existe; 48 correções já feitas de "atendido" para "faltou" |
| Registrar reposição | Não explicitamente na planilha | Implementar `reposicoes_sessao` table ou field em `consumo_sessao` |

**Tarefas:**
- [ ] Revisar interface de marcação de presença/falta (botõesclaros na tela de appointment)
- [ ] Garantir que 48 correções de status já foram aplicadas no banco
- [ ] Implementar link "registrar falta" na tela de consulta
- [ ] Implementar link "registrar reposição" se houver pacote envolvido

### 3.3.2 Cadastro e Controle de Pacotes de Sessão

| Dado da Planilha | LJ SmartClinic | Implementação |
|------------------|----------------|---------------|
| Pacotes de sessão | `pacotes_sessao` table (369 registros) | Já existe; precisar melhorar interface de visualização |
| Sessões restantes | Cálculo: total - consumido | Implementar field/method `restantes()` em `PacoteSessao` |
| Tipos de pacote | Pilates, massagem, fisioterapia, laser | Já cadastrado via `services` ou `procedimentos` |

**Tarefas:**
- [ ] Melhorar tela de visualização de pacotes do cliente (minhas sessões restantes)
- [ ] Implementar method `PacoteSessao::restantes($clienteId)` 
- [ ] Implementar method `PacoteSessao::consumidas($clienteId)`
- [ ] Criar view `minhas-sessoes.blade.php` ou componente Livewire
- [ ] Testar fluxo: cliente tem pacote de 10 sessões, usa 3, vê 7 restantes

### 3.3.3 Registro de Evolução e Anotações

| Dado da Planilha | LJ SmartClinic | Implementação |
|------------------|----------------|---------------|
| Observações por consulta | Em PAGAMENTOS.xlsx, notas variadas por linha | Estender `evolucoes_sessao` ou adicionar field `anotacoes` em `appointments` |
| Área do tratamento | Em DEPILAÇÃO LASER, campo ÁREA (AXILA, VIRILHA, etc.) | Já existe em `appointments.area`; melhorar seleção no formulário |

**Tarefas:**
- [ ] Adicionar campo `anotacoes` (text) em `appointments` ou criar relação `hasOne EvolucaoSessao`
- [ ] Melhorar seleção de ÁREA no formulário de novo appointment (lista: AXILA, VIRILHA, ABDOMEN, COXA, BRAÇO, ROSTO, etc.)
- [ ] Implementar tela de anotações por consulta (opcional, para profissional preencher)

### 3.3.4 Historial Completo do Cliente

O cliente Daiane deve conseguir ver, em uma tela:

1. **Ultimos atendimentos** — Lista das 10 últimas consultas
2. **Pacotes ativos** — Quais pacotes tem, quantas sessões usadas/restantes
3. **Histórico de presença** — Quantas compareceu, faltou, total
4. **Histórico de pagamentos** — Consultas pagas, valores, formas de pagamento
5. **Próximos agendamentos** - Consultas futuras

**Tarefas:**
- [ ] Criar tela `perfil-cliente.blade.php` ou componente Livewire `ClientProfile`
- [ ] Consulta SQL/Query unificada: appointments + consumos_sessoes + pagamentos por cliente
- [ ] Filtros por período (últimos 30 dias, este ano, todos)
- [ ] Exportar histórico (PDF ou CSV)

## 3.4 Componentes Técnicos para Implementação

### 3.4.1 Model Adjustments

#### PacoteSessao Model (ajustes)

```php
// app/Models/PacoteSessao.php
public function scopeAtivos($query)
{
    return $query->where('clinic_id', clinicId())
                 ->where('status', 'ativo')
                 ->where('data_validade', '>', now());
}

public function consumidas($clienteId)
{
    return $this->hasMany(ConsumoSessao::class)->where('clinic_id', clinicId())
                  ->where('patient_id', $clienteId)
                  ->whereNotNull('data_consumo');
}

public function restantes($clienteId)
{
    $total = $this->quantidade_sessoes; // field no model
    $consumidas = ConsumoSessao::where('pacote_sessao_id', $this->id)
                                ->where('patient_id', $clienteId)
                                ->count();
    return max(0, $total - $consumidas);
}

public function podeConsumir($clienteId, $quantidade = 1)
{
    return $this->restantes($clienteId) >= $quantidade;
}
```

#### ConsumoSessao Model (ajustes)

```php
// app/Models/ConsumoSessao.php
protected $fillable = [
    'pacote_sessao_id',
    'patient_id',
    'appointment_id',
    'data_consumo',
    'area_tratamento',
    'status', // 'consumido', 'reposicao', 'cancelado'
];

public function paciente()
{
    return $this->belongsTo(User::class, 'patient_id');
}

public function pacote()
{
    return $this->belongsTo(PacoteSessao::class);
}

public function appointment()
{
    return $this->belongsTo(Appointment::class);
}
```

### 3.4.3 Controller: AtendimentoController

Novo controller ou extensão do `AgendaController` para gerenciar o fluxo de atendimento:

```php
// app/Http/Controllers/AtendimentoController.php

public function store(Request $request)
{
    $validated = $request->validate([
        'paciente_id' => 'required|exists:users,id',
        'profissional_id' => 'required|exists:professionals,id',
        'servico' => 'required|string|max:100',
        'data' => 'required|date|after_or_equal:today',
        'area_tratamento' => 'nullable|string|max:50',
        'observacoes' => 'nullable|string|max:500',
    ]);
    
    // Verificar se paciente tem pacote ativo
    $pacoteAtivo = PacoteSessao::ativos()->whereHas('consumidas', function ($q) use ($request->paciente_id) {
        $q->where('patient_id', $request->paciente_id);
    })->first();
    
    $appointment = Appointment::create([
        'clinic_id' => clinicId(),
        'patient_id' => $request->paciente_id,
        'professional_id' => $request->profissional_id,
        'service' => $request->servico,
        'date' => $request->data,
        'area' => $request->area_tratamento,
        'status' => 'agendado',
        'observations' => $request->observacoes,
    ]);
    
    // Se tiver pacote ativo, criar registro de consumo
    if ($pacoteAtivo) {
        if ($pacoteAtivo->podeConsumir($request->paciente_id)) {
            ConsumoSessao::create([
                'pacote_sessao_id' => $pacoteAtivo->id,
                'patient_id' => $request->paciente_id,
                'appointment_id' => $appointment->id,
                'data_consumo' => $request->data,
                'status' => 'consumido',
            ]);
        }
    }
    
    return redirect()
        ->route('atendimento.index')
        ->with('success', 'Atendimento registrado com sucesso.');
}

public function marcarPresente($appointmentId)
{
    $appointment = Appointment::find($appointmentId);
    $appointment->status = 'atendido';
    $appointment->save();
    
    return back()->with('success', 'Presença registrada com sucesso.');
}

public function marcarFalta($appointmentId)
{
    $appointment = Appointment::find($appointmentId);
    $appointment->status = 'faltou';
    $appointment->save();
    
    return back()->with('success', 'Falta registrada com sucesso.');
}
```

### 3.4.4 Views para Atendimento

#### Tela de Novo Atendimento

```blade
<x-card title="Novo Atendimento">
    <x-slot name="header">
        <x-icon name="stethoscope" class="icon" /> Atendimento
    </x-slot>
    
    <form wire:submit.prevent="registrar">
        <x-row>
            <x-col span="6">
                <x-label for="paciente">{{ __('Paciente') }}</x-label>
                <x-select id="paciente" wire:model="paciente_id" required>
                    @foreach($pacientes as $patient)
                        <option value="{{ $patient->id }}">{{ $patient->name }}</option>
                    @endforeach
                </x-select>
            </x-col>
            <x-col span="6">
                <x-label for="profissional">{{ __('Profissional') }}</x-label>
                <x-select id="profissional" wire:model="profissional_id" required>
                    @foreach($profissionais as $prof)
                        <option value="{{ $prof->id }}">{{ $prof->name }}</option>
                    @endforeach
                </x-select>
            </x-col>
        </x-row>
        
        <x-row>
            <x-col span="6">
                <x-label for="data">{{ __('Data') }}</x-label>
                <x-input type="date" id="data" wire:model="data" required />
            </x-col>
            <x-col span="6">
                <x-label for="servico">{{ __('Serviço') }}</x-label>
                <x-select id="servico" wire:model="servico" required>
                    <option value="Depilação a laser">Depilação a laser</option>
                    <option value="Pilates">Pilates</option>
                    <option value="Massagem">Massagem</option>
                    <option value="Fisioterapia">Fisioterapia</option>
                    <option value="Outro">Outro</option>
                </x-select>
            </x-col>
        </x-row>
        
        <x-row>
            <x-col span="6">
                <x-label for="area_tratamento">{{ __('Área do Tratamento') }}</x-label>
                <x-select id="area_tratamento" wire:model="area_tratamento">
                    <option value="">Selecione a área</option>
                    <option value="AXILA">Axila</option>
                    <option value="VIRILHA">Virilha</option>
                    <option value="ABDOMEN">Abdomen</option>
                    <option value="COXA">Coxa</option>
                    <option value="BRAÇO">Braço</option>
                    <option value="ROSTO">Rosto</option>
                    <option value="OUTRO">Outro</option>
                </x-select>
            </x-col>
            <x-col span="6">
                <x-label for="observacoes">{{ __('Observações') }}</x-label>
                <x-textarea id="observacoes" wire:model="observacoes" rows="3" /></x-col>
        </x-row>
        
        <x-actions>
            <x-button type="primary">{{ __('Registrar Atendimento') }}</x-button>
        </x-actions>
    </form>
</x-card>
```

#### Tela de Minhas Sessões (Pacote)

```blade
<x-card title="Minhas Sessões">
    <x-slot name="header">
        <x-icon name="package" class="icon" /> Minhas Sessões
    </x-slot>
    
    @foreach($pacotesAtivos as $pacote)
        <x-card-header>
            <x-card-title>{{ $pacote->nome }}</x-card-title>
            <x-card-subtitle>{{ $pacote->quantidade_sesseis }} sessões | {{ $pacote->restantes($cliente->id) }} restantes | Vence: {{ $pacote->data_validade->format('d/m/Y') }}</x-card-subtitle>
        </x-card-header>
        
        <x-row>
            <x-col span="8">
                <x-label>Sessões consumidas: {{ $pacote->consumidas($cliente->id) }}</x-label>
            </x-col>
            <x-col span="4">
                <x-button href="{{ route('minhas-sessoes.show', $pacote->id) }}">
                    Ver detalhes
                </x-button>
            </x-col>
        </x-row>
        
        <x-row>
            <x-col span="12">
                <x-button type="link" href="{{ route('consumos.create', $pacote->id) }}>
                    Nova sessão consumida
                </x-button>
            </x-col>
        </x-row>
    @endforeach
</x-card>
```

### 3.4.5 JavaScript/AJAX para Geração de Horários

Ao selecionar um profissional e data, listar horários disponíveis:

```javascript
// Em resources/js/app.js ou inline na view
function carregarHorariosDisponiveis() {
    const profissionalId = $('#profissional').val();
    const data = $('#data').val();
    
    if (!profissionalId || !data) {
        $('#horario').empty();
        return;
    }
    
    $.ajax({
        url: '/agenda/horarios-disponiveis',
        method: 'GET',
        data: { profissional_id: profissionalId, data: data },
        dataType: 'json',
        success: function(horarios) {
            const select = $('#horario');
            select.empty();
            select.append('<option value="">Selecione o horário</option>');
            horarios.forEach(h => {
                select.append(`<option value="${h}">${h}</option>`);
            });
        },
        error: function() {
            alert('Erro ao carregar horários');
        }
    });
}
```

## 3.5 Relação Planilha ↔ LJ SmartClinic (Resumo)

| Planilha | Dado | Tabela LJ | Campo/Fluxo | Classificação |
|----------|------|-----------|-------------|---------------|
| DEPILAÇÃO LASER | Área do tratamento | `appointments.area` | Seleção em formulário | A (já existe, melhoria UI) |
| DEPILAÇÃO LASER | PRESENTE/AUSENTE | `appointments.status` | Botões Presente/Falta | A (já existe) |
| DEPILAÇÃO LASER | Valor a receber | `appointments.valor` + `payments.valor` | Já lançado | A (já existe) |
| PGTO PILATES | Tabela cruzada client×mês | `pacotes_sessao` + `consumo_sessao` | Reformatar dados/interface | C (estrutura difere) |
| PGTO PILATES | Presença (PRESENTE) | `consumo_sessao.status` | Novo field/relatório | B (parcial) |
| AGENDA CLINICA | Área massagem | `payments.servico = 'MASSAGEM'` | Já lançado | A (já existe) |
| PLANO SAÚDE | Registro de NF/pago | `insurance_plans` + `payments` | Já existente | B (parcial) |

## 3.6 Entregáveis da Etapa 3

### 3.6.1 Modelos Ajustados

- `app/Models/PacoteSessao.php` — methods `restantes()`, `consumidas()`, `escopoAtivos()`
- `app/Models/ConsumoSessao.php` — novo model ou ajuste se já existir
- `app/Models/Appointment.php` — adicionar fields/if needed: `anotacoes`, melhorar `area` selection

### 3.6.2 Controladores

- `app/Http/Controllers/AtendimentoController.php` — novo controller ou ajuste em `AgendaController`
- Métodos: `store`, `marcarPresente`, `marcarFalta`, `historicoCliente`

### 3.6.3 Views

- `resources/views/atendimento/novo.blade.php` — formulário de novo atendimento
- `resources/views/atendimento/historico-cliente.blade.php` — histórico do cliente
- `resources/views/pacotes/minhas-sessoes.blade.php` — pacotes e sessões restantes
- `resources/js/` — scripts para carregamento de horários disponíveis

### 3.6.4 Rotas

```php
Route::get('/atendimento', [AtendimentoController::class, 'index'])->name('atendimento.index');
Route::post('/atendimento', [AtendimentoController::class, 'store'])->name('atendimento.store');
Route::put('/atendimento/{appointment}/presente', [AtendimentoController::class, 'marcarPresente'])->name('atendimento.marcar.presente');
Route::put('/atendimento/{appointment}/falta', [AtendimentoController::class, 'marcarFalta'])->name('atendimento.marcar.falta');
Route::get('/pacotes/minhas', [PacoteSessaoController::class, 'minhas'])->name('minhas.sessoes');
Route::post '/pacotes/{pacote}/consumir', [ConsumoSessaoController::class, 'store'])->name('consumos.store');
```

### 3.6.4 Testes

- Criar novo atendimento com pacote ativo
- Marcar presença e falta
- Verificar sessões restantes do pacote
- Histórico do cliente completo
- Testar com variações de nome (GISELE, GISELE ALVES, etc.)

## 3.7 Riscos e Mitigações

| Risco | Gravidade | Mitigação |
|-------|-----------|-----------|
| Sessões de pacote já importadas (369) não mapeadas corretamente | Média | Auditar consumos_sessao existentes; criar script de migração se necessário; marcar sessões como 'consumidas' se já foram atendidas |
| Confusão entre pacotes de serviços diferentes (pilates vs laser vs massagem) | Média | Criar tipos/ categorias de pacote; garantir que cada pacote tenha serviço associado |
| Presença/falta não registrada consistentemente | Média | Tornar os botões "Presente" e "Falta" visíveis na tela; tornar campos obrigatórios ao salvar |
| Histórico do cliente difícil de consultar | Média | Criar view consolidada; testar consultas com dados reais; simplificar interface |
| Variações de nome confundirem busca de pacote | Média | Busca por `patient_id` (FK) ao registrar consumo; nunca buscar apenas por nome |

## 3.8 Critério de Conclusão da Etapa 3

A Etapa 3 será considerada concluída quando:

- [ ] Tela de novo atendimento funcional (cadastro de paciente, profissional, data, serviço, área)
- [ ] Presença e falta registradas corretamente (status `atendido` / `faltou`)
- [ ] Pacotes de sessão visualizáveis (quantidade total, consumidas, restantes)
- [ ] Consumo de sessão associado ao appointment ao registrar atendimento
- [ ] Histórico do cliente disponível (últimos atendimentos, pacotes ativos, pagamentos)
- [ ] Área do tratamento selecionável em formulário (AXILA, VIRILHA, ABDOMEN, etc.)
- [ ] Testes de regressão passam (pelo menos 80% dos testes de atendimento/sessões/pacotes)
- [ ] Isolamento de tenants preservado

## 3.9 Próximos Passos Após a Etapa 3

Após conclusão da Etapa 3, prosseguir para:

- **Etapa 4:** Contas a pagar e receber (implementar tabela específica, migração dos 94 suppliers)
- **Etapa 5:** Relatórios e controles administrativos (reproduzir cálculos das planilhas)
- **Etapa 6:** Validação com a cliente (protocolo de 9 tarefas críticas)

---

*Documento de plano da Etapa 3 — Atendimento, Sessões e Pacotes.*
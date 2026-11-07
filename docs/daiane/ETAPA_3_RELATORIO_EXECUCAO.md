# ETAPA 3 — Telas: Agenda, Atendimento e Histórico

## 3.1 Objetivo
Levar para o sistema o dia a dia que a Daiane registra nas abas de laser, fisioterapia, Pilates e massagem: agenda por dia e por mês, marcação de presença/falta, consumo da sessão do pacote e histórico do paciente com consultas e pagamentos.

## 3.2 Implementação das Telas

### 3.2.1 Agenda do dia (`agenda/dia.blade.php`)
- Navegação dia anterior / Hoje / próximo dia e filtro por profissional.
- Cartões de resumo: atendimentos, aguardando, atendidos e faltas.
- Lista do dia com hora, duração, paciente, serviço, área, valor previsto, profissional, sala e status.
- Botões de presença e falta para atendimentos em `agendado`, `confirmado` ou `em_atendimento`.
- Acesso ao formulário de novo agendamento e às abas Semana / Dia / Mês.

### 3.2.2 Agenda do mês (`agenda/mes.blade.php`)
- Calendário do mês (segunda a domingo), com o dia atual destacado.
- Cada dia mostra a quantidade de atendimentos e os cartões do paciente/serviço.
- Clique no dia abre a agenda daquele dia; navegação por mês anterior / Hoje / próximo mês.

### 3.2.3 Agenda semanal (`agenda/index.blade.php`)
- Visão semanal já existente, mantida como aba “Semana”.
- Filtros por profissional, sala e status; criação e edição de consulta em modal.

### 3.2.4 Criar / editar consulta (`agenda/partials/create-modal.blade.php` e `edit-modal.blade.php`)
- Campos `titulo`, `servico`, `area`, `valor`, paciente, profissional, sala, início e fim.
- Serviços e áreas comuns aparecem como sugestão (Pilates, fisioterapia, massagem, depilação a laser, limpeza de pele, design de sobrancelha).
- O formulário valida conflito de profissional e de sala antes de gravar.

### 3.2.5 Atendimentos do dia (`atendimento/index.blade.php`)
- Lista os atendimentos do dia com serviço/área, valor previsto, profissional, status e pacote do paciente.
- Mostra pacotes disponíveis com sessões restantes e o botão de consumo.
- Depois do consumo, exibe “Sessão debitada” e o pacote usado; o mesmo atendimento não debita duas vezes.
- Atalho para a agenda do dia.

### 3.2.6 Histórico do paciente (`atendimento/historico.blade.php`)
- Resumo de consultas registradas, presenças, faltas, pacotes ativos e sessões restantes.
- Tabela de consultas/atendimentos com data, serviço, profissional e status.
- Tabela de pacotes com total, usadas, restantes, status e validade.
- Tabela de pagamentos do paciente, incluindo lançamentos importados já vinculados.

### 3.2.7 Menu e módulos
- O módulo **Atendimentos** foi incluído no catálogo como módulo comum, disponível nas verticais de fisioterapia e odontologia, sem alterar os 12 módulos padronizados.
- Os menus de agenda, atendimento, financeiro e relatórios aparecem conforme a permissão do usuário.

## 3.3 Rotas Adicionadas

| Rota | Controller | Método |
|------|-----------|--------|
| `agenda.dia` | `AgendaController@dia` | GET |
| `agenda.mes` | `AgendaController@mes` | GET |
| `agenda.disponiveis` | `AgendaController@disponiveis` | GET (JSON) |
| `agenda.presenca` | `AgendaController@presenca` | POST |
| `agenda.reagendar` | `AgendaController@reagendar` | PATCH |
| `atendimentos.index` | `AtendimentoController@index` | GET |
| `atendimentos.consumir` | `AtendimentoController@consumir` | POST |
| `pacientes.historico` | `AtendimentoController@historico` | GET |

## 3.4 Regras e Exceções

| Exceção | Lançada por | Motivo |
|---------|-------------|--------|
| `AtendimentoInvalido` | `AtendimentoController@consumir` | Atendimento em status que não permite consumo |
| `PacoteSessaoIndisponivel` | `PacoteSessaoService` | Pacote cancelado, vencido ou de outro paciente |
| `SaldoDeSessoesInsuficiente` | `PacoteSessaoService` | Pacote sem sessões restantes |

Regras aplicadas:
- Falta não consome e não devolve sessão automaticamente; a reposição é um ato explícito.
- Consumo exige presença registrada (`atendido`) e é idempotente por `appointment_id`.
- O sistema não permite consumir pacote de outro paciente.

## 3.5 Testes Concluídos

| Suíte | Testes | Situação |
|-------|-------:|----------|
| `AgendaTest` | 8 | ✅ |
| `AtendimentoTest` | 7 | ✅ |

Os testes cobrem presença, falta, conflito de profissional/sala, reagendamento, horários disponíveis, agenda mensal, débito único de sessão, bloqueio de pacote de outro paciente e permissões de acesso.

## 3.6 Pendências e Validações Humanas
- **Pendência (I):** a regra comercial de reposição de faltas não foi confirmada. O sistema mantém “falta não consome e não devolve”; a reposição é manual.
- **Pendência (II):** a conferência visual das telas com a cliente ainda precisa ser feita (protocolo de `ETAPA_6_VALIDACAO.md`).
- **Validação humana:** as telas foram implementadas a partir da matriz da Etapa 1, não de uma sessão assistida com a Daiane.

## 3.7 Status
**Concluída.** Telas, rotas e regras implementadas e cobertas por testes.

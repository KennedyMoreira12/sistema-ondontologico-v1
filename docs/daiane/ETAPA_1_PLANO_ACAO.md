# ETAPA 1 — Correções das Funcionalidades Existentes

## 1.1 Objetivo
Corrigir e finalizar funcionalidades que já existem no LJ SmartClinic mas apresentam desvios em relação ao fluxo das planilhas da Daiane, preparando o sistema para a substituição completa das planilhas.

## 1.2 Itens de Correção Identificados (da auditoria)

### 1.2.1 Vínculo de Pagamento com Paciente

| Aspecto | Planilha (PAGAMENTOS.xlsx) | LJ SmartClinic (atual) | Correção Necessária |
|---------|---------------------------|------------------------|---------------------|
| Identificação do paciente | APENAS nome textual (sem FK) | `payments` table — verificar se tem `paciente_id` | Implementar campo `paciente_id` no modelo Payment e populá-lo a partir do nome da planilha, ou criar mecanismo de busca/merge de nomes |
| Volume impactado | 6.163 lançamentos | 6.163 no `clinic_000005` | Todos os registros precisam de análise |
| Classificação anterior | D (não implementado fluxo FK) | — | Buscar subir para C ou A com validação |

**Tarefas:**
- [ ] Verificar se `app/Models/Payment.php` tem `paciente_id` field
- [ ] Se não existir, adicionar campo `paciente_id` + migration
- [ ] Criar lógica de mapeamento: capturar nome do pagamento → buscar paciente por nome (com tratamento de variações: GISELE/GISELE ALVES/GISELE LVES, etc.)
- [ ] Garantir que não haja merge automático de pacientes diferentes
- [ ] Testar com registros já importados

### 1.2.2 Status de Presença e Falta

| Aspecto | Planilha (DEPILAÇÃO LASER.xlsx) | LJ SmartClinic (atual) | Correção Necessária |
|---------|--------------------------------|------------------------|---------------------|
| Campo de status | Coluna G: PRESENTE / AUSENTE / ok / FALTA PAGAR | `appointments` table — status: `agendado`, `confirmado`, `em_atendimento`, `atendido`, `faltou`, `cancelado` | 48 registros estavam como `atendido` mas deveriam ser `faltou` (já identificado na fase 2) |
| Volume impactado | 95 abas × múltiplas linhas | 1.362 appointments no `clinic_000005` | Correção já parcial, precisa de validação total |
| Classificação | A (fluxo idêntico) | — | Confirmar que correção foi aplicada e testar |

**Tarefas:**
- [ ] Confirmar se as 48 correções de status (atendido → faltou) foram aplicadas no banco
- [ ] Verificar fluxo de marcar presença/falta na interface
- [ ] Testar criação de novo appointment com status `faltou`
- [ ] Garantir que status `atendido` não seja definido automaticamente quando cliente não comparece

### 1.2.3 Variações de Nome — Sem Merge Automático

| Cenário da Planilha | Problema | Solução no LJ SmartClinic |
|--------------------|----------|---------------------------|
| GISELE / GISELE ALVES / GISELE LVES | Múltiplos registros com variação de nome | Sistema deve alertar sobre possíveis duplicatas, mas NÃO fazer merge automático. Apresentar lista de candidatos para confirmação humana. |
| JOANA DARC / JOANA DARCK / JOANA DARK | Mesmo padrão | Criar tela de "possíveis duplicatas" na busca de pacientes; permitir ao usuário confirmar qual é o correto |
| Outras variações encontradas | CARTÃO CRÉDITO, ALMOÇO, MEIA PERNA, ORNAMENTAÇÃO aparecendo em nomes de área/serviço | Implementar `limparTexto` ou validação que remova termos colados, mas preservar nomes completos |

**Tarefas:**
- [ ] Criar validação de nome que remova termos colados (CARTÃO CRÉDITO, ALMOÇO, etc.) mas preserve nomes como CONCEIÇÃO, GISELE, etc.
- [ ] Implementar tela de "possíveis duplicatas" ao buscar paciente
- [ ] NÃO executar merge automático; APEPENAS listar candidatos
- [ ] Testar busca com nomes variados

### 1.2.4 Validações de Contas (CONTAS .xlsx)

| Aspecto | Planilha (CONTAS .xlsx) | LJ SmartClinic (atual) | Correção Necessária |
|---------|-------------------------|------------------------|---------------------|
| Validações de lista | `list em G3:G75 → "PAGO,ATRASADO,"`; `list em E3:E1008 → "NF,BOLETO,DUPLICATA,OUTROS"` | `suppliers` + `payments` tables — validações limitadas | Estender validações para campos de status de pagamento e fornecedor |
| Fórmulas de cálculo | `DAYS360`, `TODAY()`, `SUM` | Nenhum cálculo de datas equivalente no código | Implementar cálculo de dias vencidos e totais mensais consistentes com planilha |
| Classificação | D (tabela de contas a pagar não existe) | — | Subir para C após implementação da Etapa 4 (contas a pagar/receber) |

**Tarefas:**
- [ ] Implementar enum/validação para status: `PAGO`, `ATRASADO`, `NF`, `BOLETO`, `DUPLICATA`, `OUTROS`
- [ ] Implementar cálculo de dias vencidos (`DAYS360` equivalent)
- [ ] Conectar validações nos controllers de financeiro

### 1.2.5 Fórmulas de Desconto/Juros

| Planilha | LJ SmartClinic | Ação |
|----------|----------------|------|
| `DAYS360(B4,A4)` — dias entre data e vencimento | Não existente | Implementar helper `diasVencimento($dataVencimento, $dataAtual)` |
| `SUM(C3:C23)` totais por mês | Já parcialmente existente em `FinanceiroController` | Garantir que totais mensais batem com planilha |
| Juros sobre vencimento | Fórmulas em abas OUTUBRO 2023 em diante | Implementar lógica de juros calculada sobre valor × taxa × dias |

**Tarefas:**
- [ ] Criar classe/helpers de cálculo financeiro
- [ ] Implementar cálculo de dias de atraso
- [ ] Implementar cálculo de juros (taxa % × dias × valor)
- [ ] Implement cálculo de desconto à vista

## 1.3 Entregáveis da Etapa 1

### 1.3.1 Migrações Necessárias
```php
// database/migrations/2026_11_XX_XXXXX_add_paciente_id_to_payments.php
Schema::table('payments', function (Blueprint $table) {
    $table->unsignedBigInteger('paciente_id')->nullable()->after('observacoes');
    $table->foreign('paciente_id')->references('id')->on('patients')->onDelete('set null');
});

// database/migrations/2026_11_XX_XXXXX_add_status_to_appointments.php
// (se não existir já) - garantir fields de status

// database/migrations/2026_11_XX_XXXXX_add_status_to_suppliers.php
Schema::table('suppliers', function (Blueprint $table) {
    $table->enum('status', ['PAGO', 'ATRASADO', 'NF', 'BOLETO', 'DUPLICATA', 'OUTROS'])->default('PAGO');
});
```

### 1.3.2 Modelos Alterados
- `app/Models/Payment.php` — adicionar `protected $fillable = [..., 'paciente_id';]` e relação `belongsTo(User/Paciente)`
- `app/Models/Appointment.php` — garantir fields `status` e methods `marcarPresente()`, `marcarFalta()`
- `app/Models/Supplier.php` — adicionar field `status` com enum

### 1.3.3 Controladores/Requests Alterados
- `app/Http/Controllers/FinanceiroController.php` — ajustes em `store()` e `update()` para preencher `paciente_id` automaticamente ou via busca
- `app/Http/Requests/FinanceiroStoreRequest.php` — adicionar validação de nome → paciente busca
- `app/Http/Controllers/AgendaController.php` — ajustes em store para status presença/falta

### 1.3.4 Tests de Regressão
- [ ] Testar importação/pagamento com nomes variados (GISELE, GISELE ALVES, GISELE LVES)
- [ ] Testar criação de appointment com status `faltou`
- [ ] Testar busca de paciente por nome com variações
- [ ] Testar cálculo de dias de atraso e juros
- [ ] Testar validações de status de fornecedor/pagamento

### 1.3.5 Critério de Conclusão da Etapa 1
- Pagamentos têm campo `paciente_id` preenchido ou buscável sem merge automático
- Status de presença/falta fluem corretamente (48 correções já aplicadas confirmadas)
- Variações de nome são tratadas (alertam, não mergeiam)
- Validações de status (PAGO/ATRASADO/NF/BOLETO/DUPLICATA/OUTROS) funcionam
- Cálculos de dias/vencimento/juros são consistentes com planilhas
- Suite de testes de regressão passa (pelo menos 80% dos testes relacionados a financeiro/agenda)

## 1.4 Ordem de Execução Recomendada

1. **Primeira semana:** Adicionar `paciente_id` migration + model adjustment + lógica de busca de nome
2. **Segunda semana:** Implementar validações de status (PAGO/ATRASADO/NF/BOLETO/DUPLICATA/OUTROS) em suppliers e payments
3. **Terceira semana:** Criar/helpers de cálculo financeiro (dias vencimento, juros, desconto)
4. **Quarta semana:** Criar testes de regressão e executar suite completa
5. **Quinta semana:** Revisão e correção de falhas encontradas nos testes

## 1.5 Riscos e Mitigações

| Risco | Gravidade | Mitigação |
|-------|-----------|-----------|
| Quebra de pagamentos existentes ao add `paciente_id` | Alta | Fazer migration `nullable();`; testar com dados de backup; `onDelete('set null')` |
| Merge automático de pacientes por nome semelhante | Alta | **NÃO fazer merge automático**; APEPENAS listar candidatos para confirmação |
| Cálculos de juros/desconto diferentes da planilha | Média | Testar fórmulas mês a mês contra CONTAS .xlsx e PAGAMENTOS .xlsx; ajustar taxa/parâmetros |
| Interface de busca de paciente confusa com variações | Média | Criar tela limpa com "possíveis duplicatas" explicando as variações encontradas |

---

*Documento gerado para orientação da Etapa 1 da implementação de substituição de planilhas Daiane.*
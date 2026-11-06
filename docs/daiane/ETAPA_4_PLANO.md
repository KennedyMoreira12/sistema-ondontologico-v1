# ETAPA 4 — Contas a Pagar e Receber

## 4.1 Objetivo
Implementar estrutura específica de contas a pagar e receber, substituindo a situação atual onde `CONTAS .xlsx` foi importado apenas como `suppliers` (94 favorecidos) sem um controle específico de "a pagar". Esta etapa cria tabelas, validações e fluxos financeiros que reproduzem as 50 abas mensais da planilha CONTAS .xlsx.

## 4.2 Diagnóstico da Planilha (já auditado)

### 4.2.1 Visão Geral: 50 abas (meses de 2023 a 2026)

| Característica | Valor |
|----------------|-------|
| Total de abas | 50 (JANEIRO 2023 a DEZEMBRO 2025 + 01/2026, 02/2026, 03/2026) |
| Estrutura padrão por aba | L3: A=DATA DE HOJE | B=VENCIMENTOS | C=DIAS PASSADOS | D=CONTAS | E=TIPO | F=VALOR | G=PAGAMENTO | H=STATUS |
| Validações de lista | `list em G3:G75 → "PAGO,ATRASADO,"`; `list em E3:E1008 → "NF,BOLETO,DUPLICATA,OUTROS"` |
| Fórmulas comuns | `TODAY()`, `DAYS360(B4,A4)`, `SUM(C4:C23)` |
| Autofiltro | Em algumas abas (ex: `$C$3:$C$22`) |
| Tipos de registro | FORNECEDOR, CONTA DE ENERGIA, ALUGUEL, EMPRESTIMO, etc. |
| Exemplos de linhas | L4: A=46327 | B=44896.0 | C=1410 | D=CARTAO BB EMPRESARIAL | E=BOLETO | F=1016.0 | G=PAGO | H=1016.0 |

**Problema identificado na auditoria:** "CONTAS .xlsx virou só suppliers (94 favorecidos): o sistema não tem tabela de contas a pagar."

### 4.2.2 Tipos de Valores Encontrados

| Tipo de Registro | Exemplos | Frequência |
|------------------|----------|------------|
| FORNECEDOR | CARTAO BB EMPRESARIAL, ALGUEL CLINICA | Alto |
| EMPRESTIMO | FIES, outras financias | Médio |
| ENERGIA ELÉTRICA | CONTA DE LUZ, IPTU | Médio |
| ALUGUEL | ALUGUEL CLINICA | Alto |
| SERVIÇOS DIVERSOS | DESIGN DE SOBRANCELHA, MATERIAL DE LIMPEZA | Baixo |

### 4.2.3 Períodos Cobertos

| Período | Observação |
|----------|------------|
| 2023 (todos os meses) | Dados completos |
| 2024 (todos os meses) | Dados completos |
| 2025 (todos os meses) | Dados completos |
| 01/2026, 02/2026, 03/2026 | Dados parciais (janeiro a março) |

## 4.3 O Que o LJ SmartClinic Já Tem

| Funcionalidade | Componente | Status |
|----------------|------------|--------|
| Tabela `suppliers` (favorecidos) | 94 registros | ⚡ Já existe (importado de CONTAS .xlsx) |
| Tabela `payments` | 6.163 registros | ⚡ Já existe |
| Tabela `accounts_receivable` | Verificar existência | ❓ Não confirmado |
| Validação de status (PAGO/ATRASADO) | Limitada | ⚡ Parcial |
| Lançamento de despesas | Via interface financeira | ⚡ Já existe |

## 4.3 O Que Precisa Ser Implementado

### 4.3.1 Nova Tabela: `accounts_payable`

Essa tabela substituirá a função atual de `suppliers` isolado e proporcionará controle de "contas a pagar" com vencimento, status e histórico.

**Migração planejada:**

```php
// database/migrations/2026_11_XX_XXXXX_create_accounts_payable.php
Schema::create('accounts_payable', function (Blueprint $table) {
    $table->id();
    $table->unsignedBigInteger('supplier_id')->nullable(); // para ligar ao supplier existente
    $table->foreign('supplier_id')->references('id')->on('suppliers')->onDelete('set null');
    
    $table->string('nome_conta')->nullable(); // nome da conta/descrição (ex: "CONTA DE LUZ", "ALUGUEL CLINICA")
    $table->decimal('valor', 10, 2);
    $table->date('data_vencimento');
    $table->date('data_pagamento')->nullable();
    
    $table->enum('status', ['PENDENTE', 'PAGO', 'ATRASADO', 'NF', 'BOLETO', 'DUPLICATA', 'OUTROS']);
    
    $table->enum('tipo', ['FORNECEDOR', 'ENERGIA', 'ALUGUEL', 'EMPRESTIMO', 'OUTRO']);
    
    $table->string('observacoes')->nullable();
    
    $table->unsignedBigInteger('clinic_id');
    $table->foreign('clinic_id')->references('id')->on('clinics')->onDelete('cascade');
    
    $table->timestamps();
});
```

**Campos e suas origens na planilha:**

| Campo da migration | Origem na planilha CONTAS .xlsx |
|--------------------|--------------------------------|
| `nome_conta` | Coluna D (CONTAS) - nomes como "CARTAO BB EMPRESARIAL", "ALUGUEL CLINICA", "ED MEDICAL 1" |
| `valor` | Coluna F (VALOR) - valores numéricos |
| `data_vencimento` | Coluna A (DATA DE HOJE) + lógica de vencimento |
| `status` | Coluna H (STATUS) - validações list: "PAGO", "ATRASADO"; ou inferido |
| `tipo` | Coluna E (TIPO) - "NF", "BOLETO", "DUPLICATA", ou categorias como "FORNECEDOR", "ALUGUEL" |
| `supplier_id` | Coluna D pode servir para ligar ao suppliers existente (mesmo nome) |

### 4.3.2 População da Tabela (Migração de Dados)

A partir dos 50 arquivos da planilha CONTAS .xlsx, é necessário:

1. Ler cada aba (mês) extraíndo linhas de dados
2. Para cada linha: criar registro em `accounts_payable` com:
   - `nome_conta` = coluna D (nome da conta)
   - `valor` = coluna F (valor)
   - `data_vencimento` = calcular a partir da data de referência do mês
   - `status` = coluna H (PAGO/ATRASADO) ou inferir de data/valor
   - `tipo` = coluna E (NF, BOLETO, DUPLICATA, ou categorizar)
   - `supplier_id` = opcional, buscar supplier existente pelo nome (coluna D)
3. Manter `suppliers` table para compatibilidade, mas passa a ser referenciada por `accounts_payable`

### 4.3.2.1 Exemplo de Dados para Migração

| Mês | Linha | Coluna D (Nome) | Coluna E (Tipo) | Coluna F (Valor) | Coluna G (Pagamento) | Coluna H (Status) |
|-----|-------|-----------------|-----------------|-------------------|----------------------|-------------------|
| JAN 2023 | 4 | CARTAO BB EMPRESARIAL | BOLETO | 1016.0 | PAGO | PAGO |
| JAN 2023 | 5 | ALGUEL CLINICA | FORNECEDOR | 1400.0 | PAGO | PAGO |
| FEV 2023 | 4 | CARTAO BB EMPRESARIAL | BOLETO | 1562.77 | PAGO | PAGO |

### 4.3.3 Validações e Rules

#### 4.3.3.1 Atualizar `Supplier` Model

Adicionar scope e métodos:

```php
// app/Models/Supplier.php
public function scopePendente($query)
{
    return $query->where('clinic_id', clinicId())
                 ->whereDoesntHave('payments', function ($q) {
                     $q->where('status', 'PAGO');
                 });
}

public function ultimoVencimento()
{
    return $this->hasMany(AccountsPayable::class)
        ->where('clinic_id', clinicId())
        ->orderBy('data_vencimento', 'desc')
        ->first();
}

public function totalPendente()
{
    return $this->hasMany(AccountsPayable::class)
        ->where('clinic_id', clinicId())
        ->where('status', 'PENDENTE')
        ->sum('valor');
}
```

#### 4.3.3.2 Request de Conta a Pagar

```php
// app/Http/Requests/AccountsPayableStoreRequest.php
public function rules(): array
{
    return [
        'nome_conta' => 'required|string|max:255',
        'valor' => 'required|numeric|min:0',
        'data_vencimento' => 'required|date',
        'tipo' => 'required|string|in:FORNECEDOR,ENERGIA,ALUGUEL,EMPRESTIMO,OUTRO',
        'status' => 'required|string|in:PENDENTE,PAGO,ATRASADO,NF,BOLETO,DUPLICATA,OUTROS',
        'observacoes' => 'nullable|string|max:1000',
    ];
}
```

### 4.3.4 Controller: FinanceiroController Ajustes

#### 4.3.4.1 Método para Criar Conta a Pagar

```php
// app/Http/Controllers/FinanceiroController.php

public function storeContaPagar(AccountsPayableStoreRequest $request)
{
    $data = $request->validated();
    
    // Buscar supplier existente pelo nome, se houver
    $supplier = null;
    if (Str::startsWith($data['nome_conta'], ['CARTAO', 'ALUGUEL', 'ENERGIA', 'EMPRESTIMO'])) {
        $supplier = Supplier::where('name', 'like', $data['nome_conta'].'%')
            ->where('clinic_id', clinicId())
            ->first();
    }
    
    $contaPagar = AccountsPayable::create([
        'supplier_id' => $supplier?->id,
        'nome_conta' => $data['nome_conta'],
        'valor' => $data['valor'],
        'data_vencimento' => $data['data_vencimento'],
        'status' => $data['status'],
        'tipo' => $data['tipo'],
        'observacoes' => $data['observacoes'],
        'clinic_id' => clinicId(),
    ]);
    
    return redirect()
        ->route('financeiro.contas-pagar.index')
        ->with('success', 'Conta a pagar registrada com sucesso.');
}
```

#### 4.3.4.2 Método Listar Contas a Pagar

```php
public function indexContasPagar(Request $request)
{
    $query = AccountsPayable::where('clinic_id', clinicId());
    
    // Filtros
    if ($request->has('status')) {
        $query->where('status', $request->status);
    }
    if ($request->has('tipo')) {
        $query->where('tipo', $request->tipo);
    }
    if ($request->has('fornecedor')) {
        $query->whereHas('supplier', function ($q) use ($request) {
            $q->where('name', 'like', '%'.$request->fornecedor.'%');
        });
    }
    if ($request->has('data_inicial')) {
        $query->where('data_vencimento', '>=', $request->data_inicial);
    }
    if ($request->has('data_final')) {
        $query->where('data_vencimento', '<=', $request->data_final);
    }
    
    $contasPagar = $query->with('supplier')
        ->orderBy('data_vencimento', 'asc')
        ->paginate(20);
    
    return view('financeiro.contas_pagar.index', compact('contasPagar'));
}
```

#### 4.3.4.3 Método Pagar Conta

```php
public function pagarConta($id)
{
    $contaPagar = AccountsPayable::where('clinic_id', clinicId())
        ->findOrFail($id);
    
    $contaPagar->status = 'PAGO';
    $contaPagar->data_pagamento = now();
    $contaPagar->save();
    
    // Criar registro de payment correspondente
    Payment::create([
        'patient_id' => null, // ou cliente específico se for taxa fixa
        'amount' => $contaPagar->valor,
        'payment_method' => strtoupper($contaPagar->tipo),
        'payment_date' => now()->format('Y-m-d'),
        'observations' => 'Pagamento de conta a pagar: '.$contaPagar->nome_conta,
        'status' => 'PAGO',
    ]);
    
    // Atualizar supplier se houver
    if ($contaPagar->supplier) {
        $contaPagar->supplier->status = 'PAGO';
        $contaPagar->supplier->save();
    }
    
    return back()->with('success', 'Conta paga e lançada com sucesso.');
}
```

### 4.3.5 Contas a Receber (Verify and Implement if Missing)

Verificar se tabela `accounts_receivable` existe. Se não existir, criar análoga a `accounts_payable` mas para receitas:

```php
// database/migrations/2026_11_XX_XXXXX_create_accounts_receivable.php
Schema::create('accounts_receivable', function (Blueprint $table) {
    $table->id();
    $table->unsignedBigInteger('patient_id')->nullable();
    $table->foreign('patient_id')->references('id')->on('users')->onDelete('set null');
    
    $table->string('descricao')->nullable();
    $table->decimal('valor', 10, 2);
    $table->date('data_emissao');
    $table->date('data_vencimento');
    $table->date('data_recebimento')->nullable();
    
    $table->enum('status', ['PENDENTE', 'RECEBIDO', 'ATRASADO']);
    $table->enum('tipo', ['CONSULTA', 'PACOTE', 'PLANO_SAUDE', 'OUTRO']);
    $table->unsignedBigInteger('clinic_id');
    $table->foreign('clinic_id')->references('id')->on('clinics')->onDelete('cascade');
    
    $table->timestamps();
});
```

## 4.4 Migração de Dados Existente (94 Suppliers)

Os atuais 94 suppliers importados de CONTAS .xlsx devem ser preservados e integrados:

```php
// No seeder ou script de migração
$suppliersExisting = Supplier::where('clinic_id', clinicId())->get();

foreach ($suppliersExisting as $supplier) {
    // Buscar lançamentos na planilha com nome similar
    $lançamentosSimilares = AccountsPayable::where('clinic_id', clinicId())
        ->where('nome_conta', 'like', '%'.$supplier->name.'%')
        ->get();
    
    if ($lançamentosSimilares->isNotEmpty()) {
        // Atualizar supplier_id nos lançamentos
        $lançamentosSimilares->each->update(['supplier_id' => $supplier->id]);
    }
}
```

## 4.5 Relação Planilha ↔ LJ SmartClinic (Resumo)

| Planilha CONTAS .xlsx | LJ SmartClinic | Classificação Antiga | Classificação Nova |
|-----------------------|----------------|----------------------|--------------------|
| 50 abas meses 2023-2025+ | `accounts_payable` table | D (não existia) | A (nova tabela implementada) |
| Validações list (PAGO/ATRASADO) | Enum status em accounts_payable | D | A |
| Tipos NF/BOLETO/DUPLICATA | Enum tipo em accounts_payable | D | A |
| 94 fornecedores importados | suppliers table + referenciação | A (isolado) | A (integrado) |
| Fórmulas DAYS360, TODAY, SUM | Lógica em helpers/controller | D | B (via código) |
| Autofiltro por mês | Filtros por data/periodo | D | A |

## 4.6 Entregáveis da Etapa 4

### 4.6.1 Migrações

1. `database/migrations/2026_11_XX_XXXXXX_create_accounts_payable.php` — tabela principal
2. `database/migrations/2026_11_XX_XXXXXX_create_accounts_receivable.php` — tabela opcional (se não existir)
3. `database/migrations/2026_11_XX_XXXXXX_add_status_to_suppliers.php` — já criado na Etapa 1

### 4.6.2 Modelos

- `app/Models/AccountsPayable.php` — novo model
- `app/Models/AccountsReceivable.php` — novo model (se criado)
- `app/Models/Supplier.php` — ajustes (scopes, methods já criados na Etapa 1)
- `app/Models/Payment.php` — ajustes (já feitos na Etapa 1)

### 4.6.3 Controladores

- `app/Http/Controllers/FinanceiroController.php` — métodos: `indexContasPagar`, `createContaPagar`, `pagarConta`, `indexContasReceber` (se houver)
- `app/Http/Requests/AccountsPayableStoreRequest.php` — novo request
- `app/Http/Requests/AccountsPayableUpdateRequest.php` — opcional

### 4.6.4 Views

- `resources/views/financeiro/contas_pagar/index.blade.php` — listagem de contas a pagar
- `resources/views/financeiro/contas_pagar/form.blade.php` — formulário de criação/edição
- `resources/views/financeiro/contas_pagar.show.blade.php` — detalhe da conta
- `resources/views/financeiro/supplier/index.blade.php` — listagem de fornecedores (atualizada)
- Possibly: `resources/views/financeiro/contas_receber/` (se houver tabela)

### 4.6.5 Rotas

```php
// routes/web.php
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/financeiro/contas-pagar', [FinanceiroController::class, 'indexContasPagar'])->name('financeiro.contas-pagar.index');
    Route::post('/financeiro/contas-pagar', [FinanceiroController::class, 'storeContaPagar'])->name('financeiro.contas-pagar.store');
    Route::put('/financeiro/contas-pagar/{id}/pagar', [FinanceiroController::class, 'pagarConta'])->name('financeiro.contas-pagar.pagar');
    Route::get('/financeiro/contas-receber', [FinanceiroController::class, 'indexContasReceber'])->name('financeiro.contas-receber.index'); // se houver
    Route::get('/suppliers', [FinanceiroController::class, 'indexFornecedores'])->name('financeiro.fornecedores.index');
});
```

### 4.6.5 Testes

- Criar conta a pagar e verificar persistência
- Filtrar por status (PENDENTE, PAGO, ATRASADO)
- Filtrar por tipo (FORNECEDOR, ENERGIA, ALCULQUE)
- Pagar conta e verificar lançamento de payment correspondente
- Verificar integração com suppliers table (94 registros)
- Testar scenarios: conta nova, conta existente (já importada), conta com/without supplier

## 4.7 Riscos e Mitigações

| Risco | Gravidade | Mitigação |
|-------|-----------|-----------|
| Dados históricos (1+ ano) perdidos ao criar nova tabela | Alta | Exportar dados atuais antes; criar script de migração that populates `accounts_payable` a partir de CONTAS .xlsx existentes; manter `suppliers` table para compatibilidade retroativa |
| Conflito entre `suppliers` nova estrutura e antiga | Média | `supplier_id` nullable em `accounts_payable`; supplier existente pode ser referenciado ou novo supplier criado |
| Fórmulas DAYS360, totais mensais não batem com planilha | Média | Testar mês a mês contra CONTAS .xlsx; ajustar parâmetros de cálculo; helpers `FinanceHelper::diasAtraso()` já criados |
| Usuário tentar lançar conta com data vencida no passado | Baixa | Validar no formulário: `data_vencimento` >= data mínima permitida (ex: data do primeiro lançamento) |
| Confusão entre contas a pagar e payments existentes | Média | Manter interface separada; `accounts_payable` para despesas futuras/vencidas; `payments` para lançamentos já realizados; descrições claras diferenciam |

## 4.8 Critério de Conclusão da Etapa 4

A Etapa 4 será considerada concluída quando:

- [ ] Tabela `accounts_payable` criada e populada (pelo menos dados dos últimos 3 meses migratórios)
- [ ] Tabela `accounts_receivable` criada (se não existir) ou alternativa implementada
- [ ] Enum status (PENDENTE, PAGO, ATRASADO, NF, BOLETO, DUPLICATA, OUTROS) funcionando
- [ ] Enum tipo (FORNECEDOR, ENERGIA, ALCULQUE, EMPRESTIMO, OUTRO) funcionando
- [ ] Interface de listagem de contas a pagar disponível com filtros
- [ ] Interface de pagamento de conta funcional (atualiza status, cria payment correspondente)
- [ ] 94 suppliers existentes integrados ou mantidos como referência
- [ ] Testes de regressão passam (pelo menos 80% dos testes de financeiro/contas)
- [ ] Isolamento de tenants preservado (clinic_000001 intocado)

## 4.8 Próximos Passos Após a Etapa 4

Após conclusão da Etapa 4, prosseguir para:

- **Etapa 5:** Relatórios e controles administrativos (reproduzir cálculos das planilhas, totais mensais, indicadores)
- **Etapa 6:** Validação com a cliente (protocolo de 9 tarefas críticas)

---

*Documento de plano da Etapa 4 — Contas a Pagar e Receber.*
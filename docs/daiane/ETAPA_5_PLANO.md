# ETAPA 5 — Relatórios e Controles Administrativos

## 5.1 Objetivo
Reproduzir os cálculos e indicadores das planilhas da Daiane no LJ SmartClinic, garantindo que totais mensais, indicadores por serviço e plano de saúde, e relatórios de presença, faltas, pacotes e recebimentos correspondam o máximo possível ao que a cliente via nas planilhas. Esta etapa foca na "reprodução fiel" dos dados, não apenas em criar relatórios genéricos.

## 5.2 Diagnóstico das Planilhas (já auditado)

### 5.2.1 Total de Receitas por Mês (PAGAMENTOS.xlsx — 69 abas)

| Mês | Total Lancamentos | Fórmulas Características |
|-----|-------------------|------------------------|
| MARÇO 2023 | 210 registros | 28 fórmulas SUM por semana |
| MAIO 2023 | 179 registros | 27 fórmulas SUM |
| JUNHO 2023 | 191 registros | 3 fórmulas (2 SUM + 1 subtração) |
| JULHO 2023 | 171 registros | 2 fórmulas |
| AGOSTO 2023 | 145 registros | 2 fórmulas |
| SETEMBRO 2023 | 155 registros | 3 fórmulas |
| OUTUBRO 2023 | 141 registros | 3 fórmulas |
| NOVEMBRO 2023 | 153 registros | 3 fórmulas |
| DEZEMBRO 2023 | 194 registros | 2 fórmulas |
| JANEIRO 2024 | 145 registros | 3 fórmulas |
| ... | ... | ... |
| DEZEMBRO 2025 | 148 registros | 31 fórmulas (mês final com mais cálculos) |

**Padrão geral:** Todas as abas têm estrutura `A=DATA | B=NOME | C=SERVIÇOS | D=TIPO PGTO | E=VALOR | F=DATA PGTO | G=VALOR RECEBIDO | I=DATA | J=NOME | K=VALOR`, com fórmulas SUM em `G` (total recebido) e `K` (total geral).

### 5.2.2 Indicadores por Serviço e Plano de Saúde

| Serviço | Frequência nas Planilhas | Campo no Sistema |
|---------|-------------------------|------------------|
| FISIOTERAPIA | Alta (múltiplos meses) | `payments.servico` |
| PILATES | Alta | `payments.servico` + `pacotes_sessao` |
| MASSAGEM | Alta | `payments.servico` |
| DEPILAÇÃO A LASER | Alta (95 abas) | `appointments.area` + `payments` |
| CONSULTA FISIOTERAPEUTA | Média | `payments.servico` |
| DESIGN DE SOBRANCELHA | Média | `payments.servico` |
| PLANO DE SAÚDE | Mencionado em alguns registros (IPSM, MAJU STORE, etc.) | `insurance_companies` + `insurance_plans` |

### 5.2.3 Relatórios Mensais Detalhados

| Tipo de Relatório | Planilha | LJ SmartClinic |
|-------------------|----------|----------------|
| Total geral mensal | `f(H194) = SUM(H11:H193)` em JUNHO 2023 | Dashboard financeiro + relatórios |
| Total por serviço | Múltiplas fórmulas COUNTIF/SUM | Agrupamento por `payments.servico` |
| Total por tipo PGTO | `SUM(G4:G8)` etc. | `payments.payment_method` group |
| Total por plano de saúde | Valores de copagamento em K | `insurance_plans` related |
| Quantidade de atendimentos | Contagem de linhas | `appointments` count by month |
| Quantidade de faltas | Contagem de `faltou` | `appointments` where status=faltou |

### 5.2.4 Relatórios de Presença e Falta

| Relatório | Planilha | LJ SmartClinic |
|-----------|----------|----------------|
| Total de consultas | Contagem de linhas por mês | `appointments` count |
| Quantidade de faltas | `f(H50) = SUM(H4:H49)` em algumas abas | `appointments` where status=faltou |
| Quantidade de presentes | Linhas totais - faltas | `appointments` where status=atendido |
| Taxa de faltas | % sobre total | Cálculo: faltos / total * 100 |

### 5.2.5 Relatórios de Pacotes e Sessões

| Relatório | Planilha | LJ SmartClinic |
|-----------|----------|----------------|
| Sessões usadas/restantes | Varie por pacote | `pacotes_sessao.quantidade - consumo_sessao.count` |
| Tipos de pacote | Pilates, massagem, etc. | `pacotes_sessao.tipo` |

### 5.2.6 Relatórios Financeiros Anuais

| Ano | Planilhas | LJ SmartClinic |
|-----|-----------|----------------|
| 2023 | 12 abas (PAGAMENTOS) + CONTAS | Dashboard anual + relatórios |
| 2024 | 12 abas (PAGAMENTOS) + CONTAS | Dashboard anual + relatórios |
| 2025 | 12 abas (PAGAMENTOS) + CONTAS | Dashboard anual + relatórios |

## 5.3 O Que o LJ SmartClinic Já Tem

| Funcionalidade | Componente | Status |
|----------------|------------|--------|
| Dashboard com métricas financeiras | `DashboardController` | ⚡ Já existe |
| Relatórios por período | `RelatorioController` + rotas | ⚡ Já existe |
| Filtros por data, profissional, serviço | Várias rotas | ⚡ Já existe |
| Exportação de relatórios | Possibly via Blade exports | ⚡ Parcial |
| Indicadores de receita | Dashboard bloco | ⚡ Já existe |
| Indicadores de atendimento | Dashboard bloco | ⚡ Já existe |

## 5.3 O Que Precisa Ser Implementado

### 5.3.1 Reprodução de Fórmulas de Soma Mensal

O desafio principal é que as planilhas usam fórmulas `SUM` entre ranges variáveis. Precisamos criar helpers que reproduzam esse comportamento.

#### 5.3.1.1 FinanceHelper — Métodos Adicionais

Já criado na Etapa 1, mas precisará de extensão:

```php
// No FinanceHelper.php - novos métodos

/**
 * Reproduzir fórmula SUM mensal como nas planilhas PAGAMENTOS.xlsx.
 * Fórmula típica: f(Hxx) = SUM(Gxxxx:Gyyyy)
 *
 * @param array $valores Array de valores do campo G (recebido) por linha
 * @param array $indices Índices das linhas incluídas no SUM (ex: [4, 30])
 * @return float Soma calculada
 */
public static function somaMensal(array $valores, array $indices): float
{
    $soma = 0;
    foreach ($indices as $indice) {
        if (isset($valores[$indice - 1])) { // -1 pq arrays são 0-indexados
            $soma += $valores[$indice - 1];
        }
    }
    return round($soma, 2);
}

/**
 * Extrair índices de uma fórmula SUM planilha como "SUM(G4:G8)"
 *
 * @param string $formula Fórmula tipo "SUM(G4:G8)"
 * @return array [linha_inicial, linha_final]
 */
public static function extrairIndicesSum(string $formula): array
{
    if (preg_match('/SUM\(G(\d+):G(\d+)\)/', $formula, $matches)) {
        return [ (int) $matches[1], (int) $matches[2] ];
    }
    return [1, 0]; // vazia
}
```

### 5.3.2 Métodos de Relatórios no Controller

#### 5.3.2.1 RelatorioController — Novos Métodos

```php
// app/Http/Controllers/RelatorioController.php

public function receitasMensais(Request $request)
{
    $query = Payment::where('clinic_id', clinicId())
        ->whereNotNull('amount');
    
    if ($request->has('mes')) {
        $query->whereMonth('payment_date', $request->mes);
    }
    if ($request->has('ano')) {
        $query->whereYear('payment_date', $request->ano);
    }
    if ($request->has('servico')) {
        $query->where('servico', $request->servico);
    }
    if ($request->has('tipo_pgto')) {
        $query->where('payment_method', $request->tipo_pgto);
    }
    
    $pagamentos = $query->get();
    
    $totalReceita = $pagamentos->sum('amount');
    $totalEntradas = $pagamentos->where('payment_method', 'PIX')->sum('amount');
    $totalDinheiro = $pagamentos->where('payment_method', 'DINHEIRO')->sum('amount');
    $totalCartao = $pagamentos->where('payment_method', 'CARTÃO DE CRÉDITO')->sum('amount');
    
    $porServico = $pagamentos->groupBy('servico')
        ->map(function ($item) {
            return $item->sum('amount');
        });
    
    $porTipoPgto = $pagamentos->groupBy('payment_method')
        ->map(function ($item) {
            return $item->sum('amount');
        });
    
    return view('relatorios.receitas-mensais', compact(
        'pagamentos', 'totalReceita', 'porServico', 'porTipoPgto'
    ));
}

public function presenciaisFaltas(Request $request)
{
    $query = Appointment::where('clinic_id', clinicId());
    
    if ($request->has('mes')) {
        $query->whereMonth('date', $request->mes);
    }
    if ($request->has('ano')) {
        $query->whereYear('date', $request->ano);
    }
    
    $total = $query->count();
    $presentes = $query->where('status', 'atendido')->count();
    $faltas = $query->where('status', 'faltou')->count();
    $agendados = $query->where('status', 'agendado')->count();
    
    $taxaFaltas = $total > 0 ? round($faltas / $total * 100, 2) : 0;
    
    return view('relatorios.presenciais-faltas', compact(
        'total', 'presentes', 'faltas', 'agendados', 'taxaFaltas'
    ));
}

public function pacotesSessoes(Request $request)
{
    $query = ConsumoSessao::where('clinic_id', clinicId());
    
    if ($request->has('mes')) {
        $query->whereMonth('data_consumo', $request->mes);
    }
    if ($request->has('ano')) {
        $query->whereYear('data_consumo', $request->ano);
    }
    
    $consumos = $query->with(['pacote', 'paciente'])
        ->get();
    
    $totalSessoes = $consumos->sum('quantidade') ?? 0; // ou count()
    $sessoesConsumidas = $consumos->count();
    $sessoesRestantes = PacoteSessao::ativos()->sum('quantidade') - $sessoesConsumidas; // simplificado
    
    return view('relatorios.pacotes-sessoes', compact(
        'consumos', 'totalSessoes', 'sessoesConsumidas', 'sessoesRestantes'
    ));
}
```

#### 5.3.2.2 Views para Relatórios

Criar views que reproduzam o layout das planilhas o máximo possível:

**receitas-mensais.blade.php:**
- Tabela com totais por serviço
- Tabela com totais por tipo de pagamento
- Comparação com valores "esperados" (se houver referência)
- Exportação em CSV

**presenciais-faltas.blade.php:**
- Tabela com total, presentes, faltas, agendados
- Taxa de faltas em destaque
- Gráfico de pizza (opcional)

**pacotes-sessoes.blade.php:**
- Lista de pacotes ativos
- Sessões usadas vs restantes
- Gráfico de consumo ao longo do tempo

### 5.3.3 Indicadores por Plano de Saúde

Muitas planilhas têm registros com identificação de plano de saúde (campo K com valores como "IPSM", "MAJU STORE", etc.).

#### 5.3.3.1 Estrutura de Dados

Verificar se `insurance_companies` e `insurance_plans` tables existem e estão preenchidas. Se não, criar:

```php
// database/migrations/2026_11_XX_XXXXXX_create_insurance_companies.php
Schema::create('insurance_companies', function (Blueprint $table) {
    $table->id();
    $table->string('nome')->unique;
    $table->string('cnpj')->nullable();
    $table->string('telefone')->nullable();
    $table->timestamps();
});

// database/migrations/2026_11_XX_XXXXXX_create_insurance_plans.php
Schema::create('insurance_plans', function (Blueprint $table) {
    $table->id();
    $table->unsignedBigInteger('insurance_company_id');
    $table->string('nome')->not_null;
    $table->string('carteirinha_padrao')->nullable();
    $table->decimal('taxa_copagamento', 5, 2)->default(0); // percentual de copagamento
    $table->timestamps();
});
```

#### 5.3.3.2 Integração com Pagamentos

Muitos pagamentos em PAGAMENTOS.xlsx têm `K` field com valores que parecem ser copagamentos ou valores totais com plano.

```php
// No Payment model ou helper
public function getValorComPlano(): ?float
{
    if ($this->insurance_plan_id) {
        return $this->amount + ($this->amount * $this->insurance_plan->taxacopagamento / 100);
    }
    return null;
}

public function getValorCopagamento(): ?float
{
    if ($this->insurance_plan_id) {
        return $this->amount * $this->insurance_plan->taxacopagamento / 100;
    }
    return null;
}
```

### 5.3.4 Relatórios de Exportação

As planilhas são nativas do Excel; o sistema deve oferecer exportação compatível:

```php
// No RelatorioController ou novo controller
public function exportarCSV($tipo, $mes = null, $ano = null)
{
    $query = Payment::where('clinic_id', clinicId());
    
    if ($mes) {
        $query->whereMonth('payment_date', $mes);
    }
    if ($ano) {
        $query->whereYear('payment_date', $ano);
    }
    
    $pagamentos = $query->get();
    
    $headers = [
        'Data',
        'Nome',
        'Serviço',
        'Tipo PGTO',
        'Valor',
        'Data PGTO',
        'Valor Recebido',
    ];
    
    $rows = $pagamentos->map(function ($p) {
        return [
            $p->payment_date->format('d/m/Y'),
            $p->patient ? $p->patient->name : $p->nome,
            $p->servico,
            $p->payment_method,
            $p->amount,
            $p->payment_date->format('d/m/Y'),
            $p->amount, // ou valor recebido field se existir
        ];
    });
    
    return Excel::download(new Collection([$headers], ...$rows), 'pagamentos_'.$mes.'_'. $ano . '.csv');
}
```

## 5.4 Componentes Técnicos para Implementação

### 5.4.1 Extensões ao FinanceHelper

Adicionar ao `app/Helpers/FinanceHelper.php`:

```php
// Método: totalizarPorMes
public static function totalizarMensais(array $registros, string $campoSoma = 'amount'): array
{
    $resultados = [];
    $meses = range(1, 12);
    
    foreach ($meses as $mes) {
        $mesRegistros = array_filter($registros, function ($r) use ($mes) {
            return $r['payment_date'] && date('m', strtotime($r['payment_date'])) == $mes;
        });
        
        $resultados[$mes] = [
            'total' => array_sum(array_column($mesRegistros, $campoSoma)),
            'quantidade' => count($mesRegistros),
            'porServico' => self::agruparPorServico($mesRegistros),
            'porTipoPgto' => self::agruparPorTipoPgto($mesRegistros),
        ];
    }
    
    return $resultados;
}

// Método: agruparPorServico
private static function agruparPorServico(array $registros): array
{
    $resultados = [];
    foreach ($registros as $r) {
        $servico = $r['servico'] ?? 'Desconhecido';
        if (!isset($resultados[$servico])) {
            $resultados[$servico] = 0;
        }
        $resultados[$servico] += $r['amount'] ?? 0;
    }
    return $resultados;
}

// Método: agruparPorTipoPgto
private static function agruparPorTipoPgto(array $registros): array
{
    $resultados = [];
    foreach ($registros as $r) {
        $tipo = $r['payment_method'] ?? 'Desconhecido';
        if (!isset($resultados[$tipo])) {
            $resultados[$tipo] = 0;
        }
        $resultados[$tipo] += $r['amount'] ?? 0;
    }
    return $resultados;
}
```

### 5.4.2 Novo Controller: RelatorioController Ajustes

Criar/atualizar `app/Http/Controllers/RelatorioController.php` com métodos completos para:
- `receitas.mensais`
- `presenciais.faltas`
- `pacotes.sessoes`
- `indicadores.plano-saude`
- `exportar.csv`

### 5.4.3 Views

Criar conjunto de views em `resources/views/relatorios/`:
- `mensais.blade.php`
- `presenciais-faltas.blade.php`
- `pacotes-sessoes.blade.php`
- `plano-saude.blade.php`
- `exportar.blade.php` (formulário de seleção)

### 5.4.4 Rotas

```php
// routes/web.php
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/relatorios', [RelatorioController::class, 'index'])->name('relatorios.index');
    Route::get('/relatorios/receitas-mensais', [RelatorioController::class, 'receitasMensais'])->name('relatorios.receitas.mensais');
    Route::get('/relatorios/presenciais-faltas', [RelatorioController::class, 'presenciaisFaltas'])->name('relatorios.presenciais.faltas');
    Route::get('/relatorios/pacotes-sessoes', [RelatorioController::class, 'pacotesSessoes'])->name('relatorios.pacotes.sessoes');
    Route::get('/relatorios/plano-saude', [RelatorioController::class, 'indicadoresPlanoSaude'])->name('relatorios.plano.saude');
    Route::get('/relatorios/exportar', [RelatorioController::class, 'exportar'])->name('relatorios.exportar');
});
```

### 5.4.5 Testes

- Gerar relatório mensal e comparar totais com planilha correspondente
- Filtrar por plano de saúde e verificar valores
- Exportar CSV e comparar estrutura com planilha
- Verificar indicadores de presença/falta vs planilha
- Testar totais anuais

## 5.5 Riscos e Mitigações

| Risco | Gravidade | Mitigação |
|-------|-----------|-----------|
| Totais não batem exatamente com planilha (arredondamentos) | Média | Documentar diferenças; as planilhas usam fórmulas SUM diretas sobre valores em formato string/float; sistema usa decimal(10,2); pequenas diferenças de centavos são esperadas |
| Fórmulas COUNTIF/SUM complexas não replicadas | Alta | Focusing em totais gerais primeiro; fórmulas individuais podem ser documentadas como "difere da planilha mas lógica equivalente implementada" |
| Dados históricos de 1+ ano precisam ser "reproduzidos" | Alta | Usar dados já importados (6.163 pagamentos + 1.362 appointments); gerar relatórios a partir desses dados; comparar mês a mês |
| Interface de relatório muito diferente da planilha | Média | Tentar manter layout semelhante (tabelas, totais, filtros); usar nomenclatura familiar (VENCIMENTOS, TOTAL, etc.) |
| Dados de plano de saúde incompletos | Média | Verificar `insurance_companies` table; se vazia, populer com empresas comuns (IPSM, MAJU STORE, etc.) baseando-se em dados das planilhas |

## 5.6 Critério de Conclusão da Etapa 5

A Etapa 5 será considerada concluída quando:

- [ ] Relatórios mensais de receitas gerados e comparados com planilhas (pelo menos 6 meses comparados)
- [ ] Indicadores de presença e faltas disponíveis e calculados corretamente
- [ ] Relatórios de pacotes e sessões funcionais
- [ ] Indicadores por plano de saúde disponíveis (se dados existirem)
- [ ] Pelo menos 3 tipos de exportação (CSV, PDF se possível) funcionando
- [ ] Testes de regressão passam (pelo menos 80% dos testes de relatórios)
- [ ] Isolamento de tenants preservado

## 5.7 Próximos Passos Após a Etapa 5

Após conclusão da Etapa 5, prosseguir para:

- **Etapa 6:** Validação com a cliente (protocolo de 9 tarefas críticas)
- **Finalização:** Apresentação final e aprovação da cliente

---

*Documento de plano da Etapa 5 — Relatórios e Controles Administrativos.*
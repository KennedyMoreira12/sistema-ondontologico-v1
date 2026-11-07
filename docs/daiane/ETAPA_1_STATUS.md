# ETAPA 1 — Status Atual e Próximos Passos

## 1.1 O Que Foi Concluído na Etapa 1

### 1.1.1 Arquivos Criados

| Arquivo | Finalidade |
|---------|------------|
| `database/migrations/2026_11_20_000001_add_paciente_id_to_payments.php` | Migration para adicionar `paciente_id` na tabela `payments` |
| `app/Models/Payment.php` | Modelo Payment com relação `patient()` e fields atualizados |
| `database/migrations/2026_11_20_000002_add_status_to_suppliers.php` | Migration para adicionar `status` enum na tabela `suppliers` |
| `app/Models/Supplier.php` | Modelo Supplier com enum de status e relação `payments()` |
| `app/Helpers/FinanceHelper.php` | Helper com funções de cálculo financeiro (dias atraso, juros, desconto, formatação BR, SUM extraction) |

### 1.1.2 Mudanças de Código Realizadas

1. **Payment model**: Adicionado `patient_id` field (nullable, com foreign key para patients table), relação `patient()`, method `getPatientNameAttribute()` para display de nome.

2. **Supplier model**: Adicionado field `status` com enum `['PAGO', 'ATRASADO', 'NF', 'BOLETO', 'DUPLICATA', 'OUTROS']`, relação `payments()`, method `getDisplayNameAttribute()`.

3. **FinanceHelper**: Classe completa com 9 métodos:
   - `diasAtraso()` — equivalente a DAYS360 planilha
   - `calcularJuros()` — cálculo de juros sobre valor atrasado (taxa × dias / 360)
   - `calcularDescontoÀVista()` — cálculo de desconto percentual
   - `calcularValorFinal()` — aplica juros ou desconto ao valor base
   - `formatarBR()` — formatação no padrão brasileiro (R$ XXX,XX)
   - `extrairSum()` — extrai e calcula fórmulas SUM como "SUM(G4:G8)"
   - `colLetterToNumber()` — converte letras de coluna para números (A=1, B=2, ..., AA=27)
   - Helper methods para integração com fórmulas das planilhas

### 1.1.3 Pendências da Etapa 1

| Pendência | Status | Proximo Passo |
|-----------|--------|---------------|
| Executar migrations no banco de desenvolvimento | ⏳ Aguardando ambiente PHP | `php artisan migrate` na máquina de dev |
| Popular `paciente_id` nos 6.163 pagamentos existentes | ⏳ Pendente | Script de busca de nome → paciente (sem merge automático) |
| Implementar validações de status no controller/formulário | ⏳ Pendente | Atualizar `FinanceiroController@store` e `FinanceiroStoreRequest` |
| Criar tela de "possíveis duplicatas" para nomes variados | ⏳ Pendente | Nova view no `resources/views/financeiro/` |
| Testes de regressão da agenda e presença | ⏳ Pendente | Suite de testes Feature/Unit |

## 1.2 Próximos Passos Imediatos

### 1.2.1 Executar Migrations

```bash
# Na máquina de desenvolvimento
php artisan migrate
```

Isso aplicará:
- `add_paciente_id_to_payments` — adiciona `paciente_id` unsignedBigInteger com foreign key
- `add_status_to_suppliers` — adiciona enum status na tabela suppliers

### 1.2.2 Popular paciente_id nos Pagamentos Existentes

Script planejado (não executado automaticamente para evitar dados incorretos):

```php
// App\Jobs\PopulatePaymentPatientIdJob ou console command
// Lógica:
// 1. Pegar cada payment com paciente_id NULL
// 2. Capturar o nome do campo 'nome' original (guardado ou inferido)
// 3. Buscar paciente por nome exato primeiro
// 4. Se não encontrar, listar candidatos (GISELE -> GISELE ALVES, GISELE LVES)
// 5. Apresentar ao usuário para escolher ou confirmar
// 6. Atualizar payment com o paciente_id selecionado
// 7. NUNCA fazer merge automático
```

### 1.2.3 Atualizar FinanceiroController

Mudanças necessárias em `app/Http/Controllers/FinanceiroController.php`:

```php
// Exemplo de ajuste no store()
public function store(FinanceiroStoreRequest $request)
{
    $data = $request->validated();
    
    // Buscar paciente pelo nome, sem merge automático
    $paciente = $this->buscarPacientePeloNome($request->nome);
    
    $payment = Payment::create([
        'patient_id' => $paciente?->id,
        'amount' => $data['valor'],
        'payment_method' => $data['tipo_pgto'],
        'payment_date' => $data['data_pgto'] ?? now(),
        'observations' => $data['observacoes'] ?? '',
        'status' => $data['status'] ?? 'PAGO',
    ]);
    
    return redirect()->route('financeiro.index')
        ->with('success', 'Pagamento registrado com sucesso.');
}

private function buscarPacientePeloNome(string $nome): ?\App\Models\User
{
    // Busca exata primeiro
    $paciente = User::where('clinic_id', clinicId())
        ->where(function ($query) use ($nome) {
            $query->where('name', $nome)
                  ->orWhere('first_name', $nome)
                  ->orWhereRaw("LOWER(name) LIKE LOWER(?)", ["%$nome%"]);
        })
        ->first();
    
    // Se não encontrado, listar candidatos (SEM merge automático)
    if (!$paciente) {
        $candidatos = User::where('clinic_id', clinicId())
            ->where(function ($query) use ($nome) {
                $query->where('name', 'LIKE', "%$nome%")
                      ->orWhereRaw("LOWER(name) LIKE LOWER(?)", ["%$nome%"]);
            })
            ->get();
        
        if ($candidatos->isEmpty()) {
            return null;
        } elseif ($candidatos->count() === 1) {
            return $candidatos->first();
        } else {
            // Retornar null e deixar para o usuário escolher na interface
            return null;
        }
    }
    
    return $paciente;
}
```

### 1.2.4 Implementar Validações de Status

Atualizar `FinanceiroStoreRequest` para incluir validação do enum de status:

```php
// app/Http/Requests/FinanceiroStoreRequest.php
public function rules(): array
{
    return [
        'nome' => 'required|string|max:255',
        'valor' => 'required|numeric|min:0',
        'tipo_pgto' => 'required|string|in:PIX,DINHEIRO,CARTÃO DE CRÉDITO,CARTÃO DE DÉBITO,NF,BOLETO,DUPLICATA,OUTROS',
        'data_pgto' => 'nullable|date',
        'observacoes' => 'nullable|string|max:1000',
        'status' => 'required|string|in:PAGO,ATRASADO,NF,BOLETO,DUPLICATA,OUTROS',
        'paciente_id' => 'nullable|exists:users,id', // opcional, pode ser buscado por nome
    ];
}
```

### 1.2.5 Testes de Regressão

Criar testes para validar:

1. **Teste de busca de paciente por nome**:
   - Busca "GISELE" deve encontrar registros, mas não fazer merge
   - Busca "GISELE ALVES" deve ser uma busca diferente
   - Lista de candidatos deve aparecer quando houver múltiplas correspondências

2. **Teste de cálculo financeiro**:
   - `FinanceHelper::diasAtraso()` deve retornar número consistente
   - `FinanceHelper::calcularJuros()` deve seguir fórmula de taxa × dias / 360
   - `FinanceHelper::formatarBR()` deve retornar "R$ XXX,XX"

3. **Teste de presença/falta**:
   - Criar appointment com status `faltou`
   - Confirmar que status `atendido` não é definido automaticamente

4. **Teste de fornecedor/status**:
   - Criar supplier com status `ATRASADO`
   - Confirmar que enum válido apenas

## 1.3 Critério de Conclusão da Etapa 1

A Etapa 1 será considerada concluída quando:

- [ ] Migrations executadas com sucesso no banco de desenvolvimento
- [ ] `paciente_id` populado ou buscável em pagamentos (sem merge automático)
- [ ] Validações de status (PAGO/ATRASADO/NF/BOLETO/DUPLICATA/OUTROS) funcionam nos forms
- [ ] Cálculos financeiros (dias atraso, juros, desconto) batem com fórmulas planilha
- [ ] Testes de regressão: pelo menos 80% dos testes relacionados a financeiro/agenda/pagamentos passam
- [ ] Documentação atualizada com decisões tomadas
- [ ] Nenhum dado da clínica Malheiro (`clinic_000001`) foi alterado
- [ ] Isolamento de tenants (`clinic_000005` Daiane vs `clinic_000001` Malheiro) preservado

## 1.4 Registro de Decisões Técnicas

| Decisão | Justificativa |
|---------|---------------|
| `paciente_id` nullable com `onDelete('set null')` | Permite pagamentos sem paciente identificado (caso nome não seja encontrado) e protege contra exclusão em cascata |
| NÃO fazer merge automático de pacientes | Auditoría explicitly determinou isso para evitar unir pessoas diferentes (GISELE vs GISELE ALVES vs GISELE LVES) |
| Enum de status com 6 valores (PAGO/ATRASADO/NF/BOLETO/DUPLICATA/OUTROS) | Cobertura completa das validações list das planilhas CONTAS .xlsx |
| FinanceHelper como classe única de cálculo | Centraliza toda lógica de fórmulas planilha, facilitando manutenção e testes |
| Busca de paciente por nome como "melhor esforço, sem merge" | Balanceia usabilidade (não perder lancamento) com integridade (não misturar clientes diferentes) |

---

*Documento de status da Etapa 1 — preparado para continuidade à Etapa 2.*
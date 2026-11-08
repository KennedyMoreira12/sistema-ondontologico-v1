# TESTES DE EQUIVALÊNCIA — PLANILHAS DA DAIANE x LJ SMARTCLINIC

Objetivo: criar testes automatizados que comparem resultados das fórmulas originais das planilhas com os cálculos do sistema, usando cópias dos dados (sem alterar a produção). Estes testes são o critério objetivo para classificar um fluxo como **A** na matriz de 137 fluxos.

---

## 1. Princípios

- Usar cópias dos dados, nunca a produção.
- Não alterar valores nem forçar igualdade.
- Comparar totais, saldos, quantidades, presenças/faltas, receitas e despesas por período.
- Testar exportações (CSV) e relatórios.
- Cada teste passa = evidência de equivalência para um fluxo da matriz.

---

## 2. Estrutura sugerida dos testes

Criar em `tests/Feature/EquivalenciaPlanilhasDaiane.php` (ou dividir por módulo):

```php
class EquivalenciaPlanilhasDaianeTest extends TestCase
{
    use RefreshDatabase, UsesTestTenant;

    // Cada método de teste = um fluxo da matriz
}
```

---

## 3. Testes por módulo

### 3.1 Agenda e horários recorrentes

| Teste | O que compara | Fluxo matriz |
|-------|--------------|--------------|
| `test_agendamento_por_data_sem_conflito` | Criar duas consultas no mesmo horário/profissional; a segunda deve falhar com conflito | AGD-03 |
| `test_visualizacao_mensal_ocupacao` | Contar consultas por dia no mês vs contagem manual da planilha CALENDÁRIO 2026 | AGD-04 |
| `test_presenca_e_falta` | Marcar atendido/faltou; verificar status no banco | PRE-01, PRE-02 |
| `test_reagendamento_mantem_conflito` | Reagendar para horário ocupado; deve falhar | AGD-11 |
| `test_lista_espera` | Adicionar paciente à lista de espera; verificar registro | AGD-06 |

### 3.2 Depilação a laser

| Teste | O que compara | Fluxo matriz |
|-------|--------------|--------------|
| `test_consulta_laser_completa` | Criar appointment com área, valor, forma pgto; criar payment vinculado; verificar totais | LAS-01 a LAS-07 |
| `test_presenca_falta_laser` | Marcar PRESENTE/AUSENTE; verificar status atendido/faltou | LAS-03 |
| `test_historico_cliente_laser` | Consultar histórico de um paciente; verificar que aparecem consultas e pagamentos | LAS-11 |
| `test_relatorio_receitas_laser_por_mes` | Comparar total do período em `receitasMensais` com soma da planilha PAGAMENTOS para o mesmo mês | LAS-14 |

### 3.3 Pilates

| Teste | O que compara | Fluxo matriz |
|-------|--------------|--------------|
| `test_pacote_pilates_nao_ft` | Criar pacote de pilates (fora vertical FT, se liberado) e verificar persistência | PIL-01 |
| `test_pagamento_pilates` | Lançar pagamento com servico=PILATES; verificar em relatório por serviço | PIL-02, PIL-07 |
| `test_total_pilates_por_mes` | Comparar `receitasMensais` filtrando PILATES com soma da planilha PGTO PILATES | PIL-08 |

### 3.4 Fisioterapia

| Teste | O que compara | Fluxo matriz |
|-------|--------------|--------------|
| `test_avaliacao_e_evolucao_ft` | Criar avaliação e evolução; verificar vínculo com paciente | FIS-03, FIS-04 |
| `test_plano_terapeutico_ft` | Criar plano com itens; verificar persistência | FIS-05 |
| `test_pacote_ft_consumo` | Criar pacote FT, consumir sessão, verificar saldo decrementado | FIS-06, FIS-07 |
| `test_consumo_idempotente_appointment` | Consumir sessão duas vezes para o mesmo appointment; deve haver apenas 1 consumo | PSP-07 |

### 3.5 Massagem

| Teste | O que compara | Fluxo matriz |
|-------|--------------|--------------|
| `test_agendamento_massagem` | Criar appointment com servico=MASSAGEM | MAS-02 |
| `test_pagamento_massagem` | Lançar pagamento servico=MASSAGEM | MAS-03 |
| `test_fluxo_caixa_massagem` | Comparar fluxo de caixa filtrando MASSAGEM com soma da planilha CAIXA | MAS-01, MAS-05 |

### 3.6 Pacientes

| Teste | O que compara | Fluxo matriz |
|-------|--------------|--------------|
| `test_cadastro_paciente` | Criar paciente; verificar listagem | PAC-01 |
| `test_busca_paciente_por_nome` | Buscar por nome parcial; verificar resultado | PAC-09 |
| `test_duplicatas_nao_fundem` | Criar dois pacientes com nomes parecidos; verificar que não são fundidos automaticamente | PAC-02, PAC-08 |
| `test_historico_paciente` | Verificar que histórico mostra consultas, pacotes, pagamentos | PAC-03 |

### 3.7 Sessões e pacotes

| Teste | O que compara | Fluxo matriz |
|-------|--------------|--------------|
| `test_pacote_saldo_nao_negativo` | Tentar consumir mais sessões que o total; deve falhar e saldo não ficar negativo | PSP-02, PSP-03 |
| `test_pacote_sessoes_restantes` | Verificar `restantes()` = total - usadas | PSP-03 |
| `test_pacote_cancelado_preserva_extrato` | Cancelar pacote; verificar que consumos permanecem | PSP-05 |
| `test_pacote_concluido_quando_zerado` | Consumir todas as sessões; verificar status concluído | PSP-06 |

### 3.8 Presença, faltas e reposições

| Teste | O que compara | Fluxo matriz |
|-------|--------------|--------------|
| `test_falta_nao_consume_sessao` | Marcar falta quando já há consumo; deve falhar | PRE-03 |
| `test_relatorio_presenca_faltas` | Comparar taxa de faltas por profissional com contagem manual | PRE-04 |

### 3.9 Contas a pagar

| Teste | O que compara | Fluxo matriz |
|-------|--------------|--------------|
| `test_conta_a_pagar_criar_baixar_quitar` | Criar conta, baixar parcial, baixar restante, verificar status pago | CP-01 a CP-05 |
| `test_baixa_acima_saldo_recusada` | Tentar baixa acima do saldo; deve ser rejeitada | CP-04 |
| `test_idempotencia_baixa` | Mesma chave de idempotência duas vezes; deve criar apenas 1 baixa | CP-06 |
| `test_atraso_calculado_nao_gravado` | Conta vencida; verificar `estaAtrasada()` e `statusEfetivo()` = atrasado, status gravado continua pendente | CP-03 |

### 3.10 Contas a receber

| Teste | O que compara | Fluxo matriz |
|-------|--------------|--------------|
| `test_conta_a_receber_listar` | Listar contas a receber; verificar paginação e busca | CR-01 |
| `test_baixa_conta_receber_parcial` | Baixa parcial; verificar status parcial e saldo | CR-03, CR-04 |
| `test_rejeita_pagamento_maior_saldo` | Pagamento acima do saldo; deve ser rejeitado | CR-05 |
| `test_total_aberto_desconta_parcial` | Total em aberto deve descontar pagamentos parciais | CR-07 |

### 3.11 Pagamentos e recebimentos

| Teste | O que compara | Fluxo matriz |
|-------|--------------|--------------|
| `test_lancamento_pagamento_estrutura` | Comparar campos DATA/NOME/SERVIÇOS/TIPO/VALOR/DATA PGTO/VALOR RECEBIDO com `payments` | PG-01, PG-02 |
| `test_vinculo_pagamento_paciente` | Vincular pagamento importado a paciente existente; verificar FK | PG-04 |
| `test_nao_sobrescreve_vinculo` | Tentar vincular pagamento já vinculado; deve falhar | PG-08 |
| `test_pagamento_manual_cria_conta_quitada` | Lançamento manual; verificar conta quitada e payment vinculado | PG-05 |
| `test_pagamentos_sem_vinculo` | Filtrar `vinculo=sem`; verificar resumo de sem vínculo | PG-06 |

### 3.12 Profissionais externos

| Teste | O que compara | Fluxo matriz |
|-------|--------------|--------------|
| `test_cadastro_profissional_externo` | Criar profissional com tipo e registro; verificar listagem | PE-01 |
| `test_comissao_profissional` | Verificar campo comissão | PE-06 |

### 3.13 Descontos, juros e vencimentos

| Teste | O que compara | Fluxo matriz |
|-------|--------------|--------------|
| `test_dias360_igual_excel` | Comparar `FinanceHelper->dias360()` com `DAYS360` do Excel para pares de data | DJ-01 |
| `test_dias_atraso` | Verificar cálculo de dias de atraso | DJ-02 |
| `test_juros_simples` | Verificar `jurosSimples` com valores conhecidos | DJ-03 |
| `test_multa` | Verificar `multa` com percentual conhecido | DJ-04 |

> Regras comerciais (taxa de juros, percentual de multa, quando aplicar) não são fixadas pelo sistema; os testes verificam que as funções calculam corretamente dado um percentual informado.

### 3.14 Relatórios e indicadores

| Teste | O que compara | Fluxo matriz |
|-------|--------------|--------------|
| `test_relatorio_receitas_por_mes` | Comparar total do período com soma manual da planilha | REL-01 |
| `test_relatorio_presenca_faltas_por_profissional` | Comparar contagem por profissional | REL-08 |
| `test_relatorio_pacotes_contratado_consumido_restante` | Verificar totais de pacotes | REL-09 |
| `test_exportacao_csv_receitas` | Exportar CSV; verificar BOM UTF-8, separador ;, dados corretos | REL-06 |
| `test_fluxo_caixa` | Comparar entradas menos saídas com fluxo de caixa manual | REL-07 |

### 3.15 Marketing e aniversariantes

| Teste | O que compara | Fluxo matriz |
|-------|--------------|--------------|
| `test_prospect_cadastrado` | Criar prospect; verificar listagem | MKT-03 |
| `test_social_post` | Criar post social; verificar persistência | MKT-05, MKT-06 |

---

## 4. Dados de teste (cópias, não produção)

Usar factories com dados baseados nas planilhas, sem copiar dados reais de produção:

| Entidade | Exemplo de dado de teste |
|----------|-------------------------|
| Paciente | 'Maria Silva da Silva', status ativo |
| Appointment | data/horário, profissional, area='AXILA', servico='DEPILAÇÃO A LASER', valor=100 |
| Payment | data, valor=210, payment_method='CARTÃO DE CRÉDITO', servico='DEPILAÇÃO À LASER' |
| PacoteSessao | nome='Pacote 10 sessões', total_sessoes=10, sessoes_usadas=0, valor_sessao=120 |
| ConsumoSessao | pacote_id, appointment_id, reposicao=false |
| AccountsPayable | nome_conta='Aluguel clínica', valor=1900, data_vencimento, status='pendente' |
| AccountsReceivable | paciente_id, vencimento, valor=500, valor_pago=0, status='aberta' |

---

## 5. Critério de aprovação de cada teste

- O resultado do sistema (total, saldo, quantidade, status) deve ser **igualmente** ao esperado calculado a partir da planilha (para os dados de teste).
- Não há tolerância de arredondamento além de 2 casas decimais (padrão monetário).
- Qualquer divergência deve ser investigada: se for diferença de formato (não de valor), classificar como B; se for valor diferente, classificar como C/D e corrigir.

---

## 6. Como executar

```bash
# Ambiente local (não produção)
cd /caminho/do/projeto
php artisan test --filter=EquivalenciaPlanilhasDaiane
# ou
vendor/bin/phpunit tests/Feature/EquivalenciaPlanilhasDaiane.php
```

---

## 7. Status atual

- Testes de funcionalidade existentes: aprovados (suíte de 714 testes, 0 falhas, 2 risky pré-existentes).
- **Testes de equivalência planilha x sistema: 44 testes criados e aprovados** em `tests/Feature/EquivalenciaPlanilhasDaianeTest.php`.
- Testes cobrem: agenda, depilação a laser, pilates, fisioterapia/pacotes, contas a pagar, contas a receber, pagamentos, FinanceHelper, relatórios (incluindo aniversariantes), pacientes, desconto, pacotes não-FT, vínculo payment ↔ conta a receber.

Quando todos os 137 fluxos forem classificados como A, a meta de 100% poderá ser declarada. Atualmente 99 de 137 fluxos são A (72,3%).

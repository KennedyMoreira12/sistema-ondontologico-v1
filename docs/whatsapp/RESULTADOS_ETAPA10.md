# Resultados de testes — Etapa 10 (Multi-Tenant e Regressão)

Data: 2026-11-02
Sessão: ses_e63c31a19ffevipW2M8hF4G9sT
Branch: feature/daiane-personalizacao (contexto preservado)
Ambiente: SQLite isolado (`DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`; `TENANT_DB_DATABASE=:memory:`)
Produção: **não alterada**

## Resumo executivo

| Métrica | Valor |
| --- | --- |
| Testes executados | 623 |
| Aprovados | 619 |
| Percentual aprovação | **99,4%** |
| Falhas | 4 |
| Risky | 2 |
| Asserções | 2384 |
| Duração | ~260.679 ms (~4 min 20 s) |

## Ambiente isolado (restrições respeitadas)

- `phpunit.xml` fixa:`DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`, `TENANT_DB_DRIVER=sqlite`, `TENANT_DB_DATABASE=:memory:`
- Nenhum banco real (`database.sqlite`) foi aberto em escrita ou alterado.
- `RefreshDatabase` em memória garante que cada conjunto de testes parte de base limpa.
- Nenhuma migration foi aplicada ao banco de produção.
- Nenhum deploy executado.
- Evolution API e n8n não modificados.
- WhatsApp / APIs externas não tocadas.

## Isolamento multi-tenant confirmados

- `TenantIsolationTest`: duas clínicas, bancos distintos (`database_name` diferente), paciente A não aparece no banco B.
- `OrdemMiddlewareTenantTest`: middleware de ordem de tenant verificado.
- `IsolamentoCacheTest`: cache isolado entre clínicas.
- Clínicas simuladas: **Daiane (`clinic_000005`)** e **Malheiro (`clinic_000001`)** — dados preservados integralmente, apenas simulados via `CreatesTenants`; nenhum registro real alterado.

## Falhas identificadas (preexistentes — regressão zero)

As 4 falhas já existiam antes de qualquer alteração da Etapa 10 (confirmado pelo baseline `docs/whatsapp/RESULTADOS_TESTES.md`). Nenhuma é nova.

1. `Tests\Feature\AuditoriaTabelasTest::test_toda_tabela_do_banco_da_clinica_esta_classificada` (linha 31)
   - Causa: `fiscal_companies`, `nfse`, `nfse_events` sem classificação vertical.
2. `Tests\Feature\AuditoriaTabelasTest::test_o_comando_de_auditoria_passa` (linha 103)
   - Causa: mesmo motivo (comando retorna 1).
3. `Tests\Feature\MigrationsFisioterapiaTest::test_a_descida_tira_o_novo_e_devolve_o_antigo` (linha 105)
   - Causa: tabela `avaliacoes` não sumiu após descida.
4. `Tests\Feature\SocialPostPanelImageTest::test_criacao_com_arquivo_envia_url_armazenada_ao_webhook` (linha 294)
   - Causa: validação `required` falha; URL armazenada diverge.

## Verificações de sintaxe, migrations e rollback

- `database/migrations/central/` e `database/migrations/tenant/` intactos.
- Nenhuma migration aplicada ao ambiente real.
- Rollback viável: `php artisan migrate:rollback` pode ser executado apenas quando autorizado, pois as alterações estão apenas no código fonte / migrations; nenhuma foi aplicada ao banco real.

## Decisões comerciais (13 pendentes)

- Todas mantidas **desativadas / configuráveis**.
- Nenhuma regra comercial inventada.
- Nenhuma ativação automatória feita nesta etapa.

## Conclusão da Etapa 10

- Suíte completa executada em SQLite isolado.
- Nenhum banco real alterado.
- Nenhuma regressão introduzida (4 falhas preexistentes preservadas).
- Isolamento multi-tenant confirmado.
- Clínicas Daiane e Malheiro preservadas.
- Relatórios criados/atualizados.
- **Próxima etapa (publicação VPS)**: aguarda autorização explícita, ativação das 13 decisões quando confirmadas, e execução de `npm run build` + deploy apenas após validação final.

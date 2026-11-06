# Resultados de testes — WhatsApp

Registro das execuções reais, com comando, resultado e leitura. Nenhum número
aqui é estimado.

## Ambiente

| Item | Valor |
| --- | --- |
| Sistema | Windows 10/11 (desenvolvimento local) |
| Branch | `feature/fisioterapia` |
| PHP de teste | 8.3.35 (`C:\Users\User\AppData\Local\Temp\opencode\php\php.exe`) |
| Banco dos testes | SQLite em memória (central) + arquivo por clínica (tenant) |
| `QUEUE_CONNECTION` nos testes | `sync` (forçado pelo `phpunit.xml`) |
| `CACHE_STORE` nos testes | `array` (forçado pelo `phpunit.xml`) |
| Produção | **não alterada**; todos os passos operacionais pendentes de autorização |

O `phpunit.xml` fixa fila e cache em modo de teste. Isso é intencional: o teste
não pode depender de worker nem de cache compartilhado, e o código precisa
funcionar igual nos dois modos.

## Baseline (antes da Fase 1)

### Subconjunto WhatsApp

```bash
php artisan test --compact <arquivos de WhatsApp>
```

| Métrica | Valor |
| --- | --- |
| Testes | 102 |
| Passaram | 102 |
| Asserções | 446 |
| Duração | ~39,7 s |

### Suíte geral

```bash
php artisan test --compact
```

| Métrica | Valor |
| --- | --- |
| Testes | 577 |
| Passaram | 573 |
| Falhas | 4 |
| Risky | 2 |
| Duração | ~215,7 s |

Falhas preexistentes (não relacionadas ao WhatsApp):

1. `AuditoriaTabelasTest::test_toda_tabela_do_banco_da_clinica_esta_classificada`
   — `fiscal_companies`, `nfse`, `nfse_events` sem classificação de vertical.
2. `AuditoriaTabelasTest::test_o_comando_de_auditoria_passa` — mesmo motivo,
   comando retorna 1.
3. `MigrationsFisioterapiaTest::test_a_descida_tira_o_novo_e_devolve_o_antigo`.
4. `SocialPostPanelImageTest::test_criacao_com_arquivo_envia_url_armazenada_ao_webhook`.

Essas quatro falhas já existiam antes de qualquer alteração de WhatsApp e
continuam iguais. Elas são registradas aqui para que uma falha nova não se
esconda atrás delas.

## Fase 1 — execuções

### Testes novos da outbox

```bash
php artisan test --compact tests/Feature/WhatsAppOutboxTest.php
```

| Métrica | Valor |
| --- | --- |
| Testes | 12 |
| Passaram | 12 |
| Asserções | 74 |
| Duração | ~4,5 s |

Cobertura:

| Teste | O que garante |
| --- | --- |
| `test_flag_desligada_preserva_o_envio_direto_e_nao_cria_outbox` | flag desligada = comportamento antigo |
| `test_flag_ligada_persiste_na_outbox_e_registra_historico_com_correlacao` | persistência antes do envio + correlação |
| `test_idempotency_key_nao_envia_a_mesma_mensagem_duas_vezes` | idempotência |
| `test_erro_5xx_agenda_retry_e_a_recuperacao_reenvia` | retry seguro |
| `test_recusa_definitiva_vai_para_dlq_e_nao_e_reenviada_pela_recuperacao` | DLQ definitiva |
| `test_clinica_sem_integracao_responde_503_e_marca_dlq` | integração ausente |
| `test_timeout_e_entrega_incerta_e_nao_e_reenviado_automaticamente` | timeout não duplica |
| `test_worker_sem_tenant_processa_a_clinica_dona_da_linha` | isolamento no worker |
| `test_webhook_de_status_atualiza_entrega_e_leitura_sem_rebaixar` | status monotônico + n8n não redispara |
| `test_recuperacao_devolve_linha_orfa_de_processing_para_a_fila` | recuperação de órfã |
| `test_replay_reprocessa_uma_linha_da_dlq` | replay manual |
| `test_saude_mostra_integracao_outbox_e_flags_sem_chamar_a_evolution` | healthcheck sem efeito externo |

### Revalidação do WhatsApp existente

```bash
php artisan test --compact tests/Feature/EvolutionWebhookTest.php tests/Feature/WhatsAppApiTest.php
```

| Métrica | Valor |
| --- | --- |
| Testes | 56 |
| Passaram | 56 |
| Asserções | 224 |
| Duração | ~12,1 s |

### Subconjunto ampliado (WhatsApp + isolamento + tenancy)

```bash
php artisan test --compact \
  tests/Feature/EvolutionWebhookTest.php \
  tests/Feature/WhatsAppApiTest.php \
  tests/Feature/WhatsAppApiIsolamentoTest.php \
  tests/Feature/WhatsAppAtendimentoIAIsolamentoTest.php \
  tests/Feature/WhatsAppAtendimentoIATest.php \
  tests/Feature/WhatsAppFluxoTest.php \
  tests/Feature/WhatsAppOutboxTest.php \
  tests/Feature/Platform/PlatformClinicWhatsAppTest.php \
  tests/Feature/Platform/PlatformProspeccaoWhatsAppTest.php \
  tests/Feature/TenantIsolationTest.php \
  tests/Feature/OrdemMiddlewareTenantTest.php \
  tests/Feature/IsolamentoCacheTest.php
```

| Métrica | Valor |
| --- | --- |
| Testes | 159 |
| Passaram | 159 |
| Asserções | 666 |
| Duração | ~33,0 s |

### Suíte geral depois da Fase 1

```bash
php artisan test --compact
```

| Métrica | Valor |
| --- | --- |
| Testes | 589 |
| Passaram | 585 |
| Falhas | 4 |
| Risky | 2 |
| Duração | ~209,1 s |

Comparação com o baseline: **+12 testes** (os novos), **+12 aprovados**, mesmas
4 falhas preexistentes, mesmas 2 risky. Nenhuma regressão.

### Regressão encontrada e corrigida

A primeira execução da suíte geral acusou uma quinta falha:
`whatsapp_outbox` sem classificação de vertical em `InventarioTabelas`. Era
regressão real da Fase 1 — a auditoria de tabelas é justamente o que impede
tabela nova de entrar sem dono. Corrigido incluindo `whatsapp_outbox` em
`InventarioTabelas::TABELAS_COMUNS`; o teste voltou a falhar só pelos três itens
preexistentes.

### Estilo

```bash
vendor/bin/pint --dirty --format agent
```

Resultado: aplicado; sem pendências de formatação nos arquivos da fase.

## Fase 2 — execuções

### Testes novos de regras, expediente e fila

```bash
php artisan test --compact tests/Feature/WhatsAppRegrasTest.php
```

| Métrica | Valor |
| --- | --- |
| Testes | 16 |
| Passaram | 16 |
| Asserções | 76 |
| Duração | ~5,0 s |

Cobertura:

| Teste | O que garante |
| --- | --- |
| `test_regra_exact_responde_e_nao_avanca_o_fluxo` | `exact` sem caixa/acento/espaço e etapa preservada |
| `test_regra_contains_respeita_prioridade_e_ignora_desativada` | prioridade e `is_active` |
| `test_regex_invalida_e_ignorada_sem_derrubar_o_atendimento` | regex quebrada não derruba o fluxo |
| `test_regex_valida_responde` | regex válida responde |
| `test_regra_nao_atropela_passo_com_contrato` | etapa `data` não é sequestrada |
| `test_comando_global_nao_e_sequestrado_por_regra` | `menu` continua indo ao fluxo |
| `test_fora_do_expediente_avisa_pausa_e_entra_na_fila_da_recepcao` | ausência + pausa até a abertura + alerta |
| `test_bot_volta_sozinho_no_horario_de_abertura` | retomada automática ao vencer o prazo |
| `test_expediente_usa_o_fuso_da_clinica_e_nao_o_da_aplicacao` | `clinics.timezone` |
| `test_opt_out_pausa_sem_prazo_e_so_volta_com_retomada_explicita` | opt-out vence regra e exige retomada |
| `test_handoff_cala_o_bot_mesmo_sem_ia` | silêncio após handoff |
| `test_conversa_na_recepcao_abre_alerta_e_handoff_resolve` | fila aberta e resolvida |
| `test_nota_interna_fica_fora_do_historico_e_da_auditoria` | nota não vira mensagem nem vaza texto |
| `test_respostas_rapidas_listam_apenas_as_ativas` | respostas prontas ativas |
| `test_fila_e_notas_exigem_ability_de_atendimento_humano` | ability `conversations.handoff` |
| `test_nota_vazia_e_recusada` | validação de nota |

### Subconjunto ampliado (WhatsApp + isolamento + tenancy)

```bash
php artisan test --compact \
  tests/Feature/EvolutionWebhookTest.php \
  tests/Feature/WhatsAppApiTest.php \
  tests/Feature/WhatsAppApiIsolamentoTest.php \
  tests/Feature/WhatsAppAtendimentoIAIsolamentoTest.php \
  tests/Feature/WhatsAppAtendimentoIATest.php \
  tests/Feature/WhatsAppFluxoTest.php \
  tests/Feature/WhatsAppOutboxTest.php \
  tests/Feature/WhatsAppRegrasTest.php \
  tests/Feature/Platform/PlatformClinicWhatsAppTest.php \
  tests/Feature/Platform/PlatformProspeccaoWhatsAppTest.php \
  tests/Feature/TenantIsolationTest.php \
  tests/Feature/OrdemMiddlewareTenantTest.php \
  tests/Feature/IsolamentoCacheTest.php
```

| Métrica | Valor |
| --- | --- |
| Testes | 175 |
| Passaram | 175 |
| Asserções | 746 |
| Duração | ~36,3 s |

### Suíte geral depois da Fase 2

```bash
php artisan test --compact
```

| Métrica | Valor |
| --- | --- |
| Testes | 605 |
| Passaram | 601 |
| Falhas | 4 |
| Risky | 2 |
| Duração | ~228,4 s |

Comparação com a Fase 1: **+16 testes** (os novos), **+16 aprovados**, mesmas
4 falhas preexistentes, mesmas 2 risky. Nenhuma regressão e nenhum teste
existente alterado.

### Correções durante a fase

1. Os models novos resolviam tabela pelo nome da classe (`whats_app_rules` etc.)
   e a primeira execução falhou com "no such table". Corrigido com `$table`
   explícito nos quatro models — o mesmo padrão de `WhatsAppConversation`.
2. `exact`/`contains` não ignoravam acento, apesar do contrato da regra.
   `MotorDeRegras::normalizar()` passou a usar `Str::ascii`, então "endereço" e
   "endereco" casam.
3. O teste novo de fuso tentava alterar o tenant por `setAttribute`; trocado por
   `update()` no registro central, que é o caminho real.

### Estilo

```bash
vendor/bin/pint --dirty --format agent
```

Resultado: aplicado; sem pendências de formatação nos arquivos da fase.

## Fase 3 — execuções

### Testes novos de menus interativos

```bash
php artisan test --compact tests/Feature/WhatsAppMenusInterativosTest.php
```

| Métrica | Valor |
| --- | --- |
| Testes | 18 |
| Passaram | 18 |
| Asserções | 112 |
| Duração | ~6,1 s |

Cobertura:

| Teste | O que garante |
| --- | --- |
| `test_flag_desligada_nao_inclui_o_contrato_interativo` | flag desligada preserva o payload antigo |
| `test_flag_ligada_devolve_botoes_no_consentimento` | `interativo` aditivo e texto intacto |
| `test_menu_depois_do_cadastro_tambem_vira_botoes` | menu da etapa vira botões |
| `test_lista_de_profissionais_usa_a_posicao_como_id` | ids por posição na ordem real da tela |
| `test_lista_de_procedimentos_respeita_o_limite_de_dez_linhas` | corte em 10 linhas |
| `test_tela_de_dia_vira_lista_com_os_dias_guardados` | lista de dias vem do estado |
| `test_tela_de_horario_vira_lista_com_os_horarios_visiveis` | lista de horários vem do estado |
| `test_titulos_repetidos_sao_desambiguados` | título repetido não derruba o menu |
| `test_flag_desligada_recusa_o_envio_interativo_e_devolve_o_texto` | 409 com `fallback_texto`, sem HTTP |
| `test_envia_botoes_para_a_evolution_e_registra_historico` | schema de `sendButtons` + histórico |
| `test_envia_lista_com_o_schema_exigido_pela_evolution` | schema de `sendList` |
| `test_recusa_do_formato_interativo_dispara_o_fallback_textual` | 4xx → texto numerado |
| `test_erro_no_corpo_da_evolution_tambem_dispara_o_fallback` | `error` no corpo → fallback |
| `test_timeout_nao_dispara_fallback_para_nao_duplicar` | timeout → 502, sem histórico |
| `test_outbox_guarda_o_menu_e_envia_como_botao` | `kind`/`payload` na outbox |
| `test_outbox_que_recusa_o_menu_reenvia_texto_na_mesma_linha` | fallback na mesma linha |
| `test_botoes_acima_do_limite_sao_recusados_na_validacao` | validação de limite |
| `test_token_sem_ability_de_envio_e_recusado` | ability `messages.send` |

### Subconjunto ampliado (WhatsApp + isolamento + tenancy)

```bash
php artisan test --compact \
  tests/Feature/EvolutionWebhookTest.php \
  tests/Feature/WhatsAppApiTest.php \
  tests/Feature/WhatsAppApiIsolamentoTest.php \
  tests/Feature/WhatsAppAtendimentoIAIsolamentoTest.php \
  tests/Feature/WhatsAppAtendimentoIATest.php \
  tests/Feature/WhatsAppFluxoTest.php \
  tests/Feature/WhatsAppOutboxTest.php \
  tests/Feature/WhatsAppRegrasTest.php \
  tests/Feature/WhatsAppMenusInterativosTest.php \
  tests/Feature/Platform/PlatformClinicWhatsAppTest.php \
  tests/Feature/Platform/PlatformProspeccaoWhatsAppTest.php \
  tests/Feature/TenantIsolationTest.php \
  tests/Feature/OrdemMiddlewareTenantTest.php \
  tests/Feature/IsolamentoCacheTest.php
```

| Métrica | Valor |
| --- | --- |
| Testes | 193 |
| Passaram | 193 |
| Asserções | 859 |
| Duração | ~38,7 s |

### Suíte geral depois da Fase 3

```bash
php artisan test --compact
```

| Métrica | Valor |
| --- | --- |
| Testes | 623 |
| Passaram | 619 |
| Falhas | 4 |
| Risky | 2 |
| Duração | ~184,9 s |

Comparação com a Fase 2: **+18 testes** (os novos), **+18 aprovados**, mesmas
4 falhas preexistentes, mesmas 2 risky. Nenhuma regressão e nenhum teste
existente alterado.

### Correções durante a fase

1. Os menus de profissional/procedimento usavam o id do banco como `id` da
   opção. O fluxo escolhe por posição, então o botão "1" apontaria para outra
   pessoa. Corrigido para a posição, com teste sobre a ordem real (`orderBy
   nome`, que coloca "Dr. Bruno" antes de "Dra. Ana").
2. O teste de timeout esperava `Http::assertSentCount(1)`; o fake que lança
   `ConnectionException` não entra em `Http::recorded()`. A asserção passou a
   verificar o efeito: 502, nenhum histórico e nenhuma linha na outbox.
3. Títulos repetidos faziam a Evolution recusar o menu inteiro. `MenuInterativo`
   passou a desambiguar com sufixo (`Dra. Ana ·2`), mantendo o id por posição.

### Estilo

```bash
vendor/bin/pint --dirty --format agent
```

Resultado: aplicado; sem pendências de formatação nos arquivos da fase.

## Limitações dos testes

- Não há teste de carga do webhook (Fase 6).
- Não há teste de contrato contra uma Evolution real; todos usam `Http::fake`.
  O schema de `sendButtons`/`sendList` da `2.3.7` foi conferido read-only no
  servidor, não por teste automatizado.
- A renderização de botões/listas no aparelho do paciente não é testável na
  suíte; o teste cobre o fallback textual, que é o que garante a mensagem.
- O workflow do n8n não é testado aqui: enquanto ele não usar `interativo`/a
  rota nova, os menus não são exercitados no atendimento real.
- Concorrência real de dois workers não é exercitada (o CAS é testado por
  comportamento, não por corrida simultânea).
- O modo `QUEUE_CONNECTION=database` com worker real não é exercitado na suíte;
  o job é chamado diretamente, que é o mesmo caminho de código.
- Nenhuma mensagem real é enviada em teste.
- O cooldown do aviso de ausência é testado com `CACHE_STORE=array`; o
  comportamento com cache compartilhado (`database`/`redis`) não é exercitado.
- Regras e triagem são testadas por requisição, não com duas conversas
  simultâneas disputando o mesmo `remote_jid`.
- Não há teste de contrato com a tela da recepção: `alertas`, `notas` e
  `respostas-rapidas` são exercitados pela API, que é o que existe hoje.

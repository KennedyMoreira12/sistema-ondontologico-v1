# AUDITORIA TÉCNICA COMPLEMENTAR — IA, HISTÓRICO E FOLLOW-UP
**LJ SmartClinic** | Fase 2

**Data:** 2025-10-20  
**Branch:** `feature/fisioterapia`  
**Repositório:** `C:\Users\User\Documents\projetos\sistema ondontologico`  
**Tipo:** Investigação estática e análise de código (sem alterações funcionais)

## 1. Resumo Executivo

O módulo de **Atendimento com IA + WhatsApp** está **parcialmente implementado e bem arquitetado**. O fluxo está em produção-ready com boas práticas de segurança (separação de responsabilidades, isolamento multi-tenant, proteção contra duplicatas, fallback para fluxo determinístico).

**Principais descobertas:**
- ✅ **Arquitetura IA bem desenhada**: `ClienteIA` (interpreta, propõe) + `AtendimentoIA` (decide, executa) com separação clara
- ✅ **Fluxo determinístico preservado**: IA nunca agenda diretamente, sempre passa por `FluxoAtendimento`
- ✅ **Proteção contra falhas**: fallback automático, controle de cota, limite por conversa
- ✅ **Histórico persistente**: `whatsapp_messages` e `whatsapp_conversations` em banco por tenant
- ✅ **Transferência para humano**: `handoff`/`retomar` implementados
- ✅ **Webhooks seguros**: Evolution webhook com autenticação dupla, deduplicação
- ⚠️ **IA como "opcional por desenho"**: Requer configuração (`AI_API_KEY` + `ATENDIMENTO_IA_ENABLED`)
- ⚠️ **Sem histórico entre conversas longas**: Contexto enviado à IA é mínimo (etapa + nome), sem histórico completo
- ⚠️ **Follow-up comercial**: Parcialmente implementado (campo `next_follow_up_at` em prospects), mas sem automação

## 2. Estado do Repositório

| Item | Valor |
|---|---|
| Branch | `feature/fisioterapia` |
| Status | Limpo (conforme instrução) |
| Últimos commits | Verificados via git log |
| Alterações locais | Nenhuma alteração funcional realizada |
| Clínica Malheiro | Preservada (não tocada) |
| Backups | Preservados (não sobrescritos) |

## 3. Inventário Técnico: IA + WhatsApp

### 3.1 Rotas API

| Rota | Método | Middleware | Controller | Função |
|---|---|---|---|---|
| `/api/whatsapp/fluxo` | POST | `AuthenticateIntegration`, `EnsureIntegrationAbility`, `plan:whatsapp`, `throttle:integration` | `WhatsAppController@fluxo` | Turno determinístico |
| `/api/whatsapp/ia` | POST | Mesmo + `plan:ai_agent` | `WhatsAppController@fluxoIA` | Turno com IA |
| `/api/whatsapp/conversa/handoff` | POST | Mesmo | `WhatsAppController@handoff` | Pausar bot, assumir humano |
| `/api/whatsapp/conversa/retomar` | POST | Mesmo | `WhatsAppController@retomar` | Retomar bot |
| `/api/evolution/webhook` | POST | `AuthenticateEvolutionWebhook`, `throttle:evolution` | `EvolutionWebhookController@handle` | Webhook entrada Evolution |
| `/api/meta/webhook/whatsapp` | GET/POST | (validação própria) | `MetaWebhookController` | Webhook Meta (WhatsApp Cloud) |

### 3.2 Serviços Principais

| Serviço | Responsabilidade | Observações |
|---|---|---|
| `App\Services\WhatsApp\ClienteIA` | Interpretação via LLM (OpenRouter/compatível OpenAI) | Propõe intenção JSON, nunca executa ações. Catálogo fechado de intenções. |
| `App\Services\WhatsApp\AtendimentoIA` | Orquestração com IA | Decide entre IA/fluxo determinístico. Gerencia cota, limites, handoff. |
| `App\Services\WhatsApp\FluxoAtendimento` | Máquina de estados determinística | Núcleo do agendamento. Sempre usado como fallback. |
| `App\Services\WhatsApp\CotaIA` | Controle de cota por clínica | Verificado antes de chamar IA |
| `App\Services\EvolutionService` | Envio via Evolution API | Envio de mensagens, gerenciamento de instâncias |
| `App\Services\N8nDispatcher` | Encaminhamento para n8n | Repasse de mensagens recebidas |

### 3.3 Models

| Model | Tabela | Contexto | Notas |
|---|---|---|---|
| `App\Models\WhatsAppConversation` | `whatsapp_conversations` (tenant) | Estado da conversa | `etapa`, `dados`, `bot_status`, `ia_mensagens`, `ia_ultimo_erro`, `expires_at` |
| `App\Models\WhatsAppMessage` | `whatsapp_messages` (tenant) | Histórico de mensagens | `message_key`+`from_me` único, `ai_intent`, `ai_handled`, `ai_error`, `forwarded_at`, `forward_error` |

## 4. Fluxo Real: Entrada → Processamento → Saída

### 4.1 Recebimento (Evolution Webhook)
1. **Autenticação**: `AuthenticateEvolutionWebhook` valida header `X-Evolution-Secret` e token da instância no corpo. Resolve clínica por `instance`.
2. **Deduplicação**: Tenta criar `WhatsAppMessage` com unique (`message_key`, `from_me`). Se violação → recarrega existente.
3. **Reserva para despacho**: UPDATE com `WHERE id=X AND forwarded_at IS NULL SET forwarded_at=NOW()` - apenas um processo reserva (compare-and-swap).
4. **Despacho**: Envia ao n8n via `N8nDispatcher`. Se n8n indisponível e recuperável → 503 "stored" (Evolution reenvia). Se não recuperável → 200 "stored".
5. **Resposta**: 200 "dispatched" ou "duplicate", 503 "stored" (com retry).

### 4.2 Processamento (API n8n → Laravel)
**Opção A - Determinístico** (`POST /api/whatsapp/fluxo`):
- `FluxoAtendimento::processar()` gerencia máquina de estados
- Etapas: consentimento → menu → cadastro → profissional → procedimento → data → horário → recepção → encerrado
- Validações numéricas, seleção por menu
- Agendamento via `AgendamentoService` com idempotência

**Opção B - Com IA** (`POST /api/whatsapp/ia`):
1. Verifica se bot ativo (`botAtivo()`). Se humano/pausado → registra mensagem, responde silencioso
2. Verifica se deve ir direto ao fluxo (etapa consentimento/cadastro/recepcao/encerrado, comandos globais, texto só numérico)
3. Verifica configuração: `ClienteIA::configurado()` E `CotaIA::disponivel()` E `ia_mensagens < max_por_conversa`
4. Reserva cota, incrementa contador `ia_mensagens`
5. Chama `ClienteIA::extrairIntencao()` com contexto mínimo (etapa, nome, clínica)
6. Se IA falhar/sem proposta → delega ao fluxo determinístico, registra erro
7. Se proposta válida → `AtendimentoIA::aplicarProposta()` decide ação:
   - `falar_humano` → muda `bot_status='aguardando_atendente'`, pausa bot
   - `agendar/horarios/profissionais/procedimentos` → se em `menu` força fluxo com "1", senão mostra info ou delega
   - `saudacao/duvida` → responde com texto IA
   - `desconhecido` → delega ao fluxo
8. Atualiza histórico da última mensagem recebida com `ai_intent`, `ai_handled`, `ai_error`

### 4.3 Envio (Resposta ao paciente)
- n8n chama `POST /api/whatsapp/mensagem` com telefone e texto
- `EvolutionService::sendText()` envia via Evolution API
- `registrarMensagemEnviada()` grava em `whatsapp_messages` com `from_me=true`
- Se Evolution retornar erro → 502, se indisponível → 503

## 5. Memória, Contexto e Histórico

| Aspecto | Status | Evidência |
|---|---|---|
| **Histórico persistente por conversa** | **A** | `whatsapp_messages` por `remote_jid`, ordenado por ID |
| **Estado da conversa** | **A** | `whatsapp_conversations` com `etapa`, `dados` (JSON), expiração 4h |
| **Contexto enviado à IA** | **C** | Apenas `etapa`, `nome` (de `dados`), `clinica`. **Não envia histórico de mensagens** |
| **Memória entre turnos** | **B** | Usa `etapa` + `dados` da conversa, mas não histórico textual completo para IA |
| **Contador de mensagens IA** | **A** | `ia_mensagens` por conversa, limite configurável (`max_por_conversa`) |
| **Metadados IA no histórico** | **A** | `ai_intent`, `ai_handled`, `ai_error` em `whatsapp_messages` |
| **Expiração de conversa** | **A** | Reset após 4h inativa, mas **preserva `bot_status`** (humano continua humano) |

**Observação crítica:** O prompt da IA (`ClienteIA::promptSistema()`) **não recebe o histórico de mensagens anteriores**. O contexto é intencionalmente mínimo por segurança (não expõe dados clínicos completos ao LLM). A memória funcional vem do `etapa` e `dados` da conversa, não do histórico textual.

## 6. Importação e Leitura de Documentos

### 6.1 Importação de Leads/Prospects
| Funcionalidade | Classificação | Evidência |
|---|---|---|
| Upload .xlsx/.csv | **A** | `Platform\ProspectImportController` com prévia e confirmação |
| Prévia dos dados | **A** | Método `preview()` |
| Validação e normalização | **A** | Implementado |
| Deduplicação | **C** | Presente, precisa validação detalhada |
| Associação correta | **A** | No banco central, separado por tenant |
| Proteção contra gravação sem confirmação | **A** | Fluxo em duas etapas |

### 6.2 Leitura de Documentos/Prontuários
| Funcionalidade | Classificação | Evidência |
|---|---|---|
| Upload de arquivos em pacientes | **C** | `PacienteController::storeDocumento()` existe, precisa ver implementação |
| PDFs/Imagens | **C** | Provavelmente sim, mas não auditado completamente |
| OCR | **D** | **Não encontrado** após busca |
| Extração por IA de documentos | **D** | **Não encontrado** |
| Processamento em filas | **E** | Não verificado |
| Importação de prontuários completos | **D** | **Não encontrado** - importação é voltada a prospects |

**Conclusão:** O sistema tem importação estruturada de leads. **Não foi encontrada** funcionalidade de OCR ou extração/interpretção por IA de prontuários completos, fichas clínicas ou documentos digitalizados. Diferente do importador de prospects.

## 7. Histórico Centralizado de Clientes

| Questão | Resposta | Evidência |
|---|---|---|
| **Histórico comercial centralizado?** | **Parcial** | `Prospects` têm `interactions()`, histórico de contatos. Separado dos pacientes. |
| **Histórico clínico separado?** | **Sim** | Pacientes vivem no banco da clínica (tenant). Prospects no banco central. Arquitetura correta. |
| **Entidade genérica de contato?** | **Não** | Não existe modelo único "Contato". Tem `Prospect` (comercial) e `Paciente` (clínico). |
| **Mesmo contato em múltiplos canais?** | **Parcial** | Possível via telefone, mas sem vínculo automático entre Prospect/Paciente |
| **Duplicidade entre entidades?** | **Possível** | Sem mecanismo óbvio de fusão. Requer cuidado. |
| **Preferências de contato / opt-out?** | **Sim** | `Prospect` tem `opted_out`, `opted_out_at`, `disinterest_reason`. WhatsApp tem verificação. |
| **Consulta autorizada sem vazar tenants?** | **Sim** | Multi-tenant bem aplicado. Prospects central, pacientes por tenant. |

**Recomendação:** **Não criar entidade genérica nova** sem análise detalhada. Avaliar extensão de `Prospect` ou relacionamento antes de duplicar.

## 8. Análise por IA - Capacidades

| Capacidade | Classificação | Evidência | Limitação |
|---|---|---|---|
| **1. Responder mensagem recebida** | **A** | `AtendimentoIA::processar()` | Requer configuração e cota |
| **2. Consultar mensagens anteriores da conversa** | **C** | Tem `WhatsAppMessage` histórico, mas **não enviado à IA** | IA só vê contexto mínimo |
| **3. Consultar registros autorizados de outros módulos** | **D** | **Não encontrado** | IA não consulta pacientes/procedimentos além do hardcoded nas respostas informativas |
| **4. Resumir histórico do cliente** | **D** | **Não encontrado** | Não implementado |
| **5. Identificar informações ausentes/contraditórias** | **D** | **Não encontrado** | Não implementado |
| **6. Sugerir próxima ação comercial** | **D** | **Não encontrado** | Não implementado |
| **7. Gerar tarefa de acompanhamento** | **D** | **Não encontrado** | Não implementado |
| **8. Executar ação automaticamente após autorização** | **C** | Handoff/retomar existem, mas IA não agenda | IA **propõe**, nunca executa ações diretas (correto por design) |

**Fatos importantes:**
- IA **NUNCA** agenda consultas diretamente - sempre delega ao fluxo determinístico
- IA só acessa `etapa`, `nome` da conversa e `nomeClinica` 
- Respostas informativas (`profissionais`, `procedimentos`, `horarios`) consultam banco diretamente em `AtendimentoIA` (sem ir ao LLM)
- Prompt proíbe diagnóstico, prescrição, preços inventados, confirmação de agendamento

## 9. Follow-up e Recuperação de Clientes

| Mecanismo | Status | Evidência | Observações |
|---|---|---|---|
| **`next_follow_up_at` em prospects** | **A** | Presente em model/banco | Usado no módulo prospecção |
| **Status comerciais em prospects** | **A** | Config `prospeccao.php` | Fluxo comercial existe |
| **Histórico de interações** | **A** | `ProspectInteraction` | Registrado |
| **Agenda (retornos)** | **A** | Módulo agenda completo | Por clínica |
| **Lembretes automáticos** | **D** | **Não encontrado** | Sem sistema de notificações/lembretes |
| **Automação de mensagens** | **D** | **Não encontrado** | Envio é manual ou via n8n |
| **Jobs agendados para follow-up** | **D** | **Não encontrado** | Scheduler não tem tarefas de follow-up identificadas |
| **Controle de opt-out** | **A** | `opted_out` em prospects | Existe |
| **Identificar quem precisa retorno** | **C** | Possível via filtros, mas não automatizado | Precisa query manual |

**Conclusão:** Tem os **campos** para follow-up, mas falta **automação** (lembretes, tarefas, campanhas). Estrutura permite extensão sem duplicação.

## 10. Integração entre Canais

| Canal | Publicação | Recebimento Mensagens | Histórico Unificado | Status |
|---|---|---|---|---|
| **WhatsApp (Evolution)** | Via Evolution API | ✅ Webhook implementado | ✅ `whatsapp_messages` | **B/A** (config pendente) |
| **WhatsApp (Meta Cloud)** | Não auditado | ✅ `MetaWebhookController` existe | Parcialmente | **C/E** |
| **Instagram** | ✅ `SocialPublisher` completo | **Não encontrado** | **Não** | **B** (só publicação) |
| **Facebook** | ✅ `SocialPublisher` completo | **Não encontrado** | **Não** | **B** (só publicação) |

**Fato importante:** Publicação em redes sociais **≠** integração de mensagens privadas. Código mostra publicação funcional, mas **não foi encontrada** integração para receber/responder DMs do Instagram/Facebook. Histórico **não está unificado** entre canais.

## 11. Segurança e Multi-Tenancy

| Aspecto | Classificação | Evidência | Observações |
|---|---|---|---|
| **Separação banco central vs por clínica** | **A** | Rotas separadas, models Central/ vs Tenant/ | Bem arquitetado |
| **Middleware tenant** | **A** | `tenant` em rotas web, `ResolveTenant` | Aplicado corretamente |
| **Autenticação API WhatsApp** | **A** | `AuthenticateIntegration` + token por clínica | Token resolve clínica, não vem no body |
| **Webhook Evolution seguro** | **A** | Dupla autenticação (header + token instância), sem ID na URL | Bem protegido |
| **Isolamento entre clínicas** | **A** | Cada clínica em seu banco, queries scoped | Testado em `WhatsAppAtendimentoIAIsolamentoTest` |
| **Bot status por conversa** | **A** | Por tenant, isolado | Testado |
| **Jobs/filas com tenant** | **C** | Precisa ver como jobs lidam com contexto | Provavelmente ok, mas não auditado completamente |
| **Cache compartilhado** | **E** | Não auditado | Potencial risco se mal configurado |
| **Clínica Malheiro** | **Protegida** | Não alterada | OK |

## 12. Testes Encontrados e Executados

### Testes relevantes identificados:
- `tests/Feature/WhatsAppFluxoTest.php` - Fluxo determinístico
- `tests/Feature/WhatsAppAtendimentoIATest.php` - IA com fallback
- `tests/Feature/WhatsAppAtendimentoIAIsolamentoTest.php` - Isolamento multi-tenant
- `tests/Feature/WhatsAppApiTest.php` - API endpoints
- `tests/Feature/EvolutionWebhookTest.php` - Webhook Evolution
- `tests/Feature/Platform/PlatformProspeccaoWhatsAppTest.php` - WhatsApp prospecção
- `tests/Feature/SocialPostPanelImageTest.php` - **FALHANDO (NÃO TOCAR)**

**Execução:** Conformemente às instruções, **não foi executada** a suíte completa. Apenas análise estática.

## 13. Tabela de Classificação

| Capacidade | Classificação | Evidência Técnica | Limitação/Pendência | Recomendação |
|---|---|---|---|---|
| **Recebimento WhatsApp (Evolution)** | **A** | `EvolutionWebhookController`, `AuthenticateEvolutionWebhook`, migrations | Config dependente | OK |
| **Atendimento IA (interpretar)** | **A** | `ClienteIA`, `AtendimentoIA`, testes | Requer `AI_API_KEY` + config | OK se configurado |
| **IA nunca agenda direto** | **A** | Código revisado - sempre delega ao fluxo | Por design, correto | Manter |
| **Transferência para humano** | **A** | `handoff()`/`retomar()`, `bot_status` | OK | OK |
| **Histórico persistente** | **A** | `whatsapp_messages`, `whatsapp_conversations` | OK | OK |
| **Isolamento multi-tenant** | **A** | Testes + arquitetura | OK | OK |
| **Deduplicação webhooks** | **A** | Unique key + compare-and-swap | OK | OK |
| **Fallback sem IA** | **A** | Lógica de delegação | OK | OK |
| **Envio via Evolution** | **A** | `EvolutionService`, `WhatsAppController@enviarMensagem` | Config dependente | OK |
| **Publicação Instagram/Facebook** | **B** | `SocialPublisher`, controllers | Precisa credenciais Meta | Configurar |
| **Recebimento DM Instagram/Facebook** | **D** | Não encontrado | Não implementado | Avaliar necessidade |
| **Importação prospects .xlsx/.csv** | **A** | `ProspectImportController` | OK | OK |
| **Importação prontuários/PDFs** | **D** | Não encontrado | Não implementado | Avaliar escopo |
| **OCR/Extração por IA** | **D** | Não encontrado | Não implementado | Avaliar necessidade |
| **Histórico unificado entre canais** | **C** | Parcial - separados por módulo | Falta vínculo | Avaliar extensão |
| **IA com histórico completo** | **C** | Contexto mínimo apenas | Por segurança/design | Considerar resumido |
| **Follow-up automatizado** | **D** | Só campos existem | Sem jobs/lembretes | Estender existente |
| **Sugestões IA para follow-up** | **D** | Não encontrado | Não implementado | Futuro |

## 14. Lacunas Comprovadas

| Lacuna | Impacto | Viabilidade | Notas |
|---|---|---|---|
| **IA não usa histórico textual completo** | Médio | Média | Enviar resumo ao invés de tudo. Cuidado com tokens/dados |
| **Sem importação de prontuários antigos** | Médio | Média | Diferente de prospects. Precisa análise jurídica/funcional |
| **Sem automação de follow-up** | Médio | Alta | Pode estender `next_follow_up_at` com notificações/jobs |
| **Sem DM unificado Instagram/Facebook** | Baixo-Médio | Baixa | Exige permissões Meta complexas |
| **Sem sistema unificado de contatos** | Baixo | Baixa | Risco de duplicação. Melhor estender existente |

## 15. Plano Incremental Recomendado (SEM IMPLEMENTAÇÃO)

### ETAPA 1: Configuração e Validação (Prioridade ALTA)
**Objetivo:** Ativar módulos já prontos  
**Aprovação necessária:** Sim

| Ação | Arquivos | Risco | Teste |
|---|---|---|---|
| Configurar Meta Graph API no painel Platform | Configuração via UI | Baixo | Verificar conexão |
| Configurar Evolution API se necessário | Configuração via UI | Baixo | Testar envio/recebimento |
| Validar `ATENDIMENTO_IA_ENABLED` + credenciais | `.env` (se autorizado) | Baixo | Testar turno IA com dados fictícios |

### ETAPA 2: Validação do IA/WhatsApp (Prioridade ALTA)
**Objetivo:** Confirmar funcionamento completo  
**Aprovação necessária:** Sim

| Ação | Arquivos | Risco | Teste |
|---|---|---|---|
| Revisar logs de webhook Evolution | Logs | Baixo | Verificar fluxo |
| Testar handoff/retomar | `WhatsAppApiTest` | Baixo | Já existem testes |
| Validar isolamento entre clínicas | `WhatsAppAtendimentoIAIsolamentoTest` | Baixo | Já existe |
| Verificar limites de cota | `CotaIA` | Baixo | Análise + teste |

### ETAPA 3: Melhorias de Follow-up (Prioridade MÉDIA)
**Objetivo:** Aproveitar estrutura existente  
**Aprovação necessária:** Sim  
**Reutiliza:** `next_follow_up_at`, `interactions`, agenda

| Ação | Arquivos Potenciais | Risco | Teste |
|---|---|---|---|
| Adicionar filtros "para hoje/atrasados" em prospecção | `ProspectController@index`, view | Baixo | Teste feature |
| Criar visão unificada de follow-ups | Novo view/controller ou estender | Baixo | Teste |
| Adicionar lembretes (se aprovado) | Job/Notification | Médio | Teste com dados fictícios |

### ETAPA 4: Histórico IA Enriquecido (Prioridade BAIXA)
**Objetivo:** Melhorar contexto sem expor dados sensíveis  
**Aprovação necessária:** Sim  
**Cuidado:** Dados clínicos, LGPD

| Ação | Arquivos Potenciais | Risco | Teste |
|---|---|---|---|
| Enviar resumo das últimas N mensagens à IA | `ClienteIA::payload()`, `AtendimentoIA` | Médio | Teste com mock |
| Resumir histórico comercial (prospects) | Novo serviço | Médio | Teste isolado |
| **NUNCA** enviar prontuário completo ao LLM | - | Alto | Revisão obrigatória |

## 16. Recomendações Finais

### Imediatas (Sem código)
1. **Configurar módulos prontos** - Instagram/Facebook e WhatsApp Prospecção
2. **Validar IA/WhatsApp em homologação** - Com dados fictícios, sem enviar mensagens reais
3. **Documentar configurações** - Mapear todas as variáveis necessárias

### Curto prazo (Com aprovação)
4. **Melhorar follow-up** - Aproveitar campos existentes, adicionar visibilidade
5. **Enriquecer contexto IA** - Apenas com resumo de mensagens não-clínicas
6. **Mapear webhooks** - Documentar todos os fluxos

### Longo prazo (Com avaliação)
7. **Avaliar importação de históricos** - Somente se houver necessidade comprovada
8. **Considerar unificação de contatos** - Com muito cuidado para não duplicar

## 17. Conclusão

O sistema possui uma **arquitetura sólida para IA + WhatsApp**. Os pontos fortes:
- Separação clara entre interpretação (IA) e execução (Laravel)
- Fallback robusto para fluxo determinístico
- Segurança multi-tenant bem implementada
- Deduplicação e tratamento de falhas maduros
- Transferência humana funcional

**Principais lacunas reais:**
- IA opera com contexto mínimo (intencional por segurança)
- Follow-up automatizado não existe
- Integração DM redes sociais não implementada
- Importação de prontuários/completa não existe

**Recomendação de ordem:** Configurar módulos prontos → Validar IA/WhatsApp → Melhorias de follow-up com base em dados existentes → Avaliar demais melhorias conforme necessidade.

---

**Relatório gerado:** 2025-10-20  
**Sem alterações funcionais, sem deploy, sem push**  
**Teste `SocialPostPanelImageTest` não modificado**  
**Clínica Malheiro e backups preservados**  
**Aguardando aprovação explícita para qualquer implementação**

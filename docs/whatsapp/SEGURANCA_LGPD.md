# Segurança e LGPD — WhatsApp

O que é dado pessoal no fluxo, onde ele fica, quem alcança e o que ainda falta.

## 1. Classificação do que trafega

| Dado | Exemplo | Sensibilidade | Onde fica |
| --- | --- | --- | --- |
| Número de telefone | `5511988887777` | dado pessoal | `whatsapp_messages.remote_jid`, `whatsapp_outbox.telefone` |
| Nome de perfil | `push_name` | dado pessoal | `whatsapp_messages.push_name` |
| Conteúdo da mensagem | texto do paciente | pode conter dado de saúde | `whatsapp_messages.body` |
| Id da mensagem no WhatsApp | `3EB0...` | identificador | `message_key`, `provider_message_id` |
| Metadados de contexto | id da mensagem citada | baixo | `whatsapp_messages.payload` |
| Menu interativo | rótulos e posição das opções | baixo | `whatsapp_outbox.payload` e texto de fallback em `whatsapp_messages.body` |
| Token da instância | segredo do gateway | credencial | `clinics.evolution_token` (cifrado) |
| Token de integração | segredo do n8n | credencial | `integration_tokens` (SHA-256) |

Dado de saúde é dado pessoal sensível. O conteúdo de uma conversa de clínica
pode revelar tratamento, diagnóstico ou condição do paciente — por isso o
histórico **nunca** vive no banco central.

## 2. Fronteiras de acesso

### Banco por clínica

`whatsapp_messages`, `whatsapp_conversations` e `whatsapp_outbox` estão no banco
da clínica. O isolamento é físico, não um `WHERE clinic_id = ?`: uma consulta
sem filtro não alcança outra clínica porque outra clínica é outro banco.

O menu interativo não acrescenta dado novo: ele repete rótulos que já aparecem
no texto (nome do profissional, procedimento, dia/horário) e guarda a posição da
opção. O contrato e o texto de fallback ficam na outbox e no histórico da
clínica, nunca no banco central.

### Banco central

Guarda apenas o que a plataforma precisa para operar: `clinics`,
`clinic_feature_flags`, `tenant_connections`, `integration_tokens`. Nenhuma
mensagem, nenhum telefone de paciente.

### Webhook público

`api/evolution/webhook` é a única rota pública que escreve em banco de clínica.
A defesa tem duas camadas independentes:

1. segredo compartilhado no header (`EVOLUTION_WEBHOOK_SECRET`);
2. token da instância no corpo, comparado com o token cifrado da clínica.

Sem o segredo configurado, a rota recusa **todo** POST — fechada por padrão.
O `throttle:evolution` limita por instância, não por IP, porque todas as
instâncias saem do mesmo servidor.

### API interna

Toda rota exige token de integração e passa por `EnsureIntegrationAbility`, que
mapeia rota → ability. Rota nova sem entrada no mapa estoura, em vez de entrar
liberada por esquecimento.

`api/whatsapp/saude` e `api/whatsapp/info` não expõem dado de paciente e são
liberadas para qualquer token válido.

## 3. Credenciais

| Credencial | Proteção |
| --- | --- |
| `evolution_token` | cast `encrypted` (APP_KEY); nunca devolvido em API ou log |
| token de integração do n8n | SHA-256; o valor em claro só existe na entrega |
| `EVOLUTION_WEBHOOK_SECRET` | `.env`; comparação em tempo constante |
| chave de IA | `.env`/config; não vai para o banco da clínica |

Regras:

- Nunca registrar token, senha, QR Code ou payload de conexão em log ou
  relatório.
- Nunca imprimir `.env` em terminal compartilhado.
- Rotação: gerar novo token na tela da integração, atualizar o n8n, depois
  revogar o antigo. A rotação do token da instância exige atualizar a clínica e
  reconectar — **operação com autorização**.

## 4. Logs

O que entra no log:

- `correlation_id` (por requisição);
- `clinic_id`, `outbox_id`, `message_key`;
- código estável da falha (`sem_integracao`, `evolution_recusou`, `incerto`);
- classe da exceção.

O que **não** entra:

- corpo da mensagem;
- telefone completo;
- token ou header de autorização;
- payload bruto da Evolution.

O `correlation_id` é aceito do cliente apenas com formato seguro
(`[A-Za-z0-9._:-]{1,64}`) e, quando inválido, é substituído — ele vai para log
e para resposta, então não pode carregar conteúdo arbitrário.

## 5. Retenção e descarte

Situação atual: **não há rotina de expurgo**. O histórico cresce com o uso.

Política a implementar na Fase 4/6, por clínica:

| Dado | Retenção sugerida | Justificativa |
| --- | --- | --- |
| `whatsapp_outbox` com `status = sent` e mais de 90 dias | expurgar | rastro operacional já cumpriu o papel |
| `whatsapp_outbox` `dead` | manter até revisão humana | é a DLQ |
| `whatsapp_messages` | definir com a clínica (padrão 24 meses) | histórico de atendimento |
| `whatsapp_notes` | enquanto a conversa estiver ativa; revisar com a clínica | anotação operacional, pode conter dado de saúde |
| `whatsapp_alerts` atendidos | 90 dias | rastro de fila e tempo de espera |
| `whatsapp_rules` e `whatsapp_quick_replies` | enquanto vigentes | configuração da clínica, não dado de titular |
| logs de aplicação | 30 a 90 dias | diagnóstico |
| `failed_jobs` | 30 dias | diagnóstico |

O expurgo precisa ser **por clínica**, respeitando o contrato dela, e nunca
apagar linha com `status` diferente de `sent`.

## 6. Direitos do titular

Pedido de acesso, correção ou exclusão é atendido no banco da clínica
correspondente. O operador da plataforma não tem acesso ao conteúdo por
padrão; quando precisar, o acesso é pontual, registrado e autorizado pela
clínica.

O que existe hoje: `audit_logs` no banco da clínica registra ações de usuário.
O que falta: registrar também acesso administrativo a conversa e exportação.

Pedido para parar de receber mensagem automática é registrado e a conversa fica
pausada sem prazo; o bot só volta com retomada explícita. A auditoria guarda o
fato e o estado, nunca o texto da conversa.

Notas internas podem conter dado de saúde e por isso ficam em tabela separada
do histórico, acessíveis apenas pela API com a ability `conversations.handoff`.
A auditoria registra quem escreveu e sobre qual conversa, sem copiar o texto.

## 7. n8n e IA

- O evento enviado ao n8n leva o necessário para responder (clínica, instância,
  mensagem, número) e **não** leva token.
- A IA recebe o texto da conversa para interpretar. Isso é transferência para
  operador: precisa estar no contrato da clínica e coberto pela política de
  privacidade.
- `CotaIA` limita custo por clínica.
- A IA não executa ação: ela devolve intenção e texto; o Laravel valida e
  executa pelo serviço existente.
- Prompt injection: o conteúdo do paciente é dado, não instrução. O prompt do
  sistema precisa deixar isso explícito e o resultado é sempre validado antes de
  virar ação.

## 8. Riscos residuais conhecidos

| Risco | Situação | Mitigação |
| --- | --- | --- |
| Sem expurgo automático | aberto | Fase 4/6, por clínica |
| Sem registro de acesso administrativo a conversa | aberto | Fase 4 |
| Webhook depende de segredo compartilhado, não de HMAC | aceito | Evolution não assina; duas camadas + throttle |
| `forwarded_at` reservado antes do n8n pode perder redespacho | conhecido | fora do escopo da F1 |
| Mídia não é baixada | aceito | sem cópia local, sem URL pública |
| Replay de DLQ pode duplicar mensagem incerta | controlado | comando manual e aviso explícito |

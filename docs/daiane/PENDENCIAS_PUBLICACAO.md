# PENDÊNCIAS PARA PUBLICAÇÃO EM PRODUÇÃO — Daiane

**Branch:** `feature/daiane-planilha-substituicao`  
**Commit base:** `b78619a` (sem push)  
**Clínica alvo:** `clinic_id=5` / `clinic_000005`  
**Status:** publicação **não autorizada** — depende de homologação com a cliente e decisão humana

---

## 1. Pendências comerciais (não inferíveis da implementação)

Estas pendências dependem da cliente Daiane e não podem ser decididas pela equipe técnica:

| # | Pendência | Situação atual | Decisão necessária |
|---|---|---|---|
| 1 | **Reposição de faltas** | A falta não consome e não devolve sessão; a reposição é um ato explícito (novo agendamento). | Confirmar se falta deve devolver sessão ao pacote ou gerar direito a reposição. |
| 2 | **Juros e multa** | Padrão 0 e configuráveis; nenhum valor comercial definido. | Confirmar percentuais de juros e multa para contas a pagar/receber atrasadas. |
| 3 | **Vínculo de pacientes antigos** | Sem merge automático por nome. Variantes como GISELE/GISELE ALVES/GISELE LVES e JOANA DARC/DARCK/DARK exigem revisão humana. | Definir quem fará a revisão dos 6.163 pagamentos importados e o prazo. |
| 4 | **NFSe (documentos fiscais)** | Tabelas e serviços existem, mas o SendNF segue mock; nenhum documento fiscal real foi emitido. | Confirmar se emissao fiscal real é escopo desta liberação ou fica para próxima fase. |

---

## 2. Pendências de validação técnica

| # | Pendência | Por que depende do banco original / da cliente |
|---|---|---|
| 5 | **6.174 lançamentos × 6.163 pagamentos** (diferença de 11) — **resolvida** | A diferença fecha aritmeticamente: 14 duplicatas internas em (data, valor, observações) colapsadas pela idempotência + 3 pagamentos pré-existentes fora da planilha = 6.174 − 14 + 3 = 6.163. Reextração confirmou 6.174 linhas / 6.160 chaves distintas. Nenhum lançamento foi apagado ou forçado. |
| 6 | **Conferência de relatórios contra as planilhas** | Totais de receitas, presença/faltas e pacotes precisam ser validados pela Daiane contra suas planilhas originais. |
| 7 | **Aplicação do protocolo de nove tarefas** (`ETAPA_6_VALIDACAO.md`) | Requer sessão agendada com a cliente e preenchimento do formulário de validação. |

---

## 3. Riscos se publicado sem resolver as pendências

- A diferença de 11 lançamentos está explicada (14 duplicatas internas + 3 pagamentos pré-existentes), mas a conferência visual das 3 linhas pré-existentes ainda depende do banco original.
- Regras de reposição de faltas e juros/multa não confirmadas podem exigir retrabalho nas telas de consumo e contas a pagar.
- Pagamentos importados sem vínculo continuarão aparecendo como "sem dono" até revisão humana.
- Volumes reais (767 pacientes, 1.362 atendimentos, 6.163 pagamentos, 94 fornecedores, 369 pacotes) não foram validados em carga no SQLite demo.

---

## 4. Checklist antes de qualquer publicação

- [ ] Suíte completa verde: `php artisan test --compact` (670 testes, 0 falhas).
- [ ] Pint verde: `vendor/bin/pint --dirty --format agent`.
- [ ] Build de frontend: `npm run build` (ou `npm run dev` / `composer run dev`, se houver alteração de assets).
- [ ] Backup do banco da clínica 5 e do código em produção.
- [ ] Homologação com a cliente concluída (≥ 8 tarefas "Sem dificuldades").
- [ ] Autorização humana explícita para publicar em produção.
- [ ] Plano de rollback definido (restauração de backup + checkout da tag anterior — não existe comando de rollback de migrations por tenant).

---

## 5. Procedimento sugerido de publicação (somente após autorização)

```bash
# 1. Confirmar integridade da branch
vendor/bin/pint --dirty --format agent
php artisan test --compact
npm run build

# 2. Backup do banco da clínica 5 e do código em produção (antes de qualquer migration)

# 3. Publicar o código pela rotina já usada pela VPS (sem --force em banco sem backup)

# 4. Aplicar migrations
php artisan migrate --force
php artisan tenants:migrate --clinic=5

# 5. Validar login, agenda, atendimentos, financeiro e relatórios com um usuário da clínica 5
```

**Rollback:** colocar sistema em manutenção → restaurar backup do banco do tenant `clinic_000005` (e central, se necessário) → checkout da tag anterior do código → rebuildar assets → reabrir e conferir login e agenda → registrar ocorrido.

---

## 6. O que NÃO fazer (regras do projeto)

- Não fazer push da branch sem autorização.
- Não fazer deploy ou migration em produção.
- Não acessar ou alterar a VPS.
- Não modificar dados reais, DNS, SSL, Evolution API, n8n ou credenciais.
- Não apagar planilhas originais.
- Não enviar mensagens WhatsApp reais nem emitir documentos fiscais reais (mock apenas).
- Não fazer merge automático de pacientes/pagamentos por nome (vínculo só por revisão humana).
- Não alterar dados ou experiência da clínica Malheiro (`clinic_id=1`).

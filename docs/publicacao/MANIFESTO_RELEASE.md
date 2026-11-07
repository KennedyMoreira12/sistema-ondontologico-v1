# MANIFESTO DE RELEASE — LJ SmartClinic

**Versão de referência:** branch `feature/daiane-planilha-substituicao`, commit `d73d2c1ae37368c32f0b063d226aecd03acfec8e`  
**Data de preparação:** 06/11/2026  
**Ambiente de preparação:** exclusivamente local (sem push, sem deploy, sem acesso à VPS)  
**Pronto para publicação:** Não — aguardando autorização humana e homologação com a Daiane

---

## 1. Identificação do release

| Campo | Valor |
|---|---|
| Branch | `feature/daiane-planilha-substituicao` |
| Commit | `d73d2c1ae37368c32f0b063d226aecd03acfec8e` |
| Mensagem | Corrige duplicação de agendamento e finaliza documentos da Daiane |
| Autor | KennedyMoreira12 |
| Data do commit | Fri Nov 6 08:30:11 2026 -0300 |
| PHP | 8.3.35 |
| Laravel Framework | 13.33.0 |
| Testes | 670 passando, 2639 asserções, 0 falhas, 2 risky |
| Pint | Verde (`--dirty --format agent`) |

---

## 2. Arquivos modificados no commit (24 arquivos)

### 2.1 Código da aplicação (14 arquivos)

| Arquivo | Alteração |
|---|---|
| `app/Console/Commands/DiagnosticarPagamentosCommand.php` | `--arquivo` opcional; diagnóstico somente leitura sem CSV |
| `app/Exceptions/AtendimentoInvalido.php` | Novas exceções: `faltaComSessaoConsumida`, `atendimentoInexistente` |
| `app/Exceptions/OperacaoFinanceiraInvalida.php` | Nova exceção: `contaComBaixas` |
| `app/Http/Controllers/AgendaController.php` | `store` usa `AgendamentoService` (lock + idempotência); `presenca` captura `AtendimentoInvalido` |
| `app/Http/Controllers/ContaPagarController.php` | Resumos usam saldo (`expressaoSaldo`); cancelamento delegado ao service |
| `app/Http/Controllers/FinanceiroController.php` | `totalAberto` desconta `valor_pago`; `fluxoCaixa` normaliza período invertido |
| `app/Http/Controllers/RelatorioController.php` | `periodo()` normaliza `de > até` |
| `app/Http/Requests/AppointmentStoreRequest.php` | Valida `idempotency_key`; ignora conflito da consulta já criada (reenvio) |
| `app/Models/AccountsPayable.php` | Novo método estático `expressaoSaldo()` |
| `app/Services/Agenda/AgendamentoService.php` | Confere idempotência dentro do lock; aceita `room_id`, `titulo`, `area`, `servico`, `valor`, `retorno` |
| `app/Services/Atendimento/AtendimentoService.php` | `marcarStatus` transacional com `lockForUpdate`; falta bloqueada após consumo |
| `app/Services/Financeiro/ContasPagarService.php` | Baixa idempotente após lock; chave de outra conta recusada; cancelamento transacional |
| `resources/views/agenda/partials/create-modal.blade.php` | Formulário gera `idempotency_key` (`Str::uuid()`) em campo oculto |

### 2.2 Testes (9 arquivos)

| Arquivo | Alteração |
|---|---|
| `tests/Feature/AgendaTest.php` | Idempotência de reenvio; agendamento sem sala |
| `tests/Feature/AtendimentoTest.php` | Falta após consumo bloqueada |
| `tests/Feature/ContaPagarTest.php` | Resumo com baixa parcial; cancelamento com baixa; idempotência entre contas |
| `tests/Feature/DemoSeederTest.php` | Idempotência do `DemoDaianeSeeder` |
| `tests/Feature/FinanceiroCommandsTest.php` | (novo) dry-run/apply e diagnóstico somente leitura |
| `tests/Feature/FinanceiroTest.php` | Total em aberto parcial; fluxo de caixa com período invertido |
| `tests/Feature/RelatorioTest.php` | Período invertido normalizado |
| `tests/Feature/TenantIsolationModulosTest.php` | (novo) Isolamento HTTP dos módulos (2 testes/20 asserções) |

### 2.3 Documentos de entrega (3 arquivos)

| Arquivo | Descrição |
|---|---|
| `docs/daiane/RELATORIO_FINAL_IMPLEMENTACAO.md` | Relatório final da implementação |
| `docs/daiane/ROTEIRO_HOMOLOGACAO_DAIANE.md` | Roteiro de homologação (nove tarefas) |
| `docs/daiane/PENDENCIAS_PUBLICACAO.md` | Pendências para publicação |

### 2.4 Documentos de publicação VPS (7 arquivos)

| Arquivo | Descrição |
|---|---|
| `docs/publicacao/PLANO_PUBLICACAO_VPS.md` | Plano de publicação |
| `docs/publicacao/COMPATIBILIDADE_SERVIDOR.md` | Compatibilidade do servidor |
| `docs/publicacao/MATRIZ_MIGRATIONS.md` | Matriz de migrations |
| `docs/publicacao/PLANO_BACKUP_RESTAURACAO.md` | Plano de backup e restauração |
| `docs/publicacao/PLANO_ROLLBACK.md` | Plano de rollback |
| `docs/publicacao/CHECKLIST_POS_PUBLICACAO.md` | Checklist pós-publicação |
| `docs/publicacao/MANIFESTO_RELEASE.md` | Este manifesto |

---

## 3. Arquivos excluídos do pacote e justificativas

| Arquivo/Diretório | Justificativa |
|---|---|
| `.env` | Contém credenciais reais; não incluir no pacote |
| `vendor/` | Dependências PHP; reinstalar via `composer install` na VPS |
| `node_modules/` | Dependências Node; reinstalar via `npm install` na VPS |
| `storage/` | Logs, cache, sessões, uploads locais; não incluir |
| `bootstrap/cache/` | Cache gerado; regenerar na VPS |
| `.agents/skills/brag/` | Artefato não versionado intencionalmente |
| `brag-output/` | Artefato não versionado intencionalmente |
| `PLANO_EXECUCAO_COMPLETO.md` | Artefato não versionado intencionalmente |
| `backup-migracao.sh` | Artefato não versionado intencionalmente |
| `backup-ps-migracao.ps1` | Artefato não versionado intencionalmente |
| `brag-output/` | Artefato não versionado intencionalmente |
| `ljsmartclinic-migracao-20261031-184952.tar.gz` | Backup pessoal, não parte da release |
| `publicar_lista.txt` | Artefato não versionado intencionalmente |
| Logs com dados pessoais | Não incluir dados sensíveis |

---

## 4. Migrations incluídas

| Migration | Ação | Risco de perda de dados |
|---|---|---|
| `add_idempotency_key_to_appointments_table` | Adiciona coluna anulável | Não |
| `create_pacotes_sessao_table` | Cria tabela nova | Não |
| `create_consumo_sessao_table` | Cria tabela nova | Não |
| `create_fiscal_tables` | Cria tabelas novas | Não |
| `add_financeiro_e_atendimento_fields_to_tables` | Adiciona colunas anuláveis | Não |
| `create_accounts_payable_tables` | Cria tabelas novas | Não |

Nenhuma migration altera ou elimina dados existentes. Todas são reversíveis.

---

## 5. SHA256 dos arquivos de documentação

Os hashes abaixo foram gerados no ambiente local de preparação. O administrador deve revalidar após copiar os arquivos para a VPS.

```
docs/daiane/RELATORIO_FINAL_IMPLEMENTACAO.md
docs/daiane/ROTEIRO_HOMOLOGACAO_DAIANE.md
docs/daiane/PENDENCIAS_PUBLICACAO.md
docs/publicacao/PLANO_PUBLICACAO_VPS.md
docs/publicacao/COMPATIBILIDADE_SERVIDOR.md
docs/publicacao/MATRIZ_MIGRATIONS.md
docs/publicacao/PLANO_BACKUP_RESTAURACAO.md
docs/publicacao/PLANO_ROLLBACK.md
docs/publicacao/CHECKLIST_POS_PUBLICACAO.md
docs/publicacao/MANIFESTO_RELEASE.md
```

*(Hashes a serem preenchidos pelo administrador após cópia para a VPS, usando `sha256sum` ou `Get-FileHash` no PowerShell.)*

---

## 6. Instruções de instalação (para o administrador na VPS)

```bash
# 1. Fazer backup completo (obrigatório)
# Consulte docs/publicacao/PLANO_BACKUP_RESTAURACAO.md

# 2. Entrar no diretório do projeto na VPS
cd /var/www/ljsmartclinic

# 3. Verificar o HEAD atual
git log -1 --format='%H %s'

# 4. Preparar diretório de release separado (não substituir a versão ativa)
mkdir -p releases/20261106-daiane
# Copiar os arquivos do commit d73d2c1 para o diretório de release

# 5. Instalar dependências de produção
composer install --no-dev --optimize-autoloader

# 6. (Opcional, apenas se houver alteração de assets)
npm install --ignore-scripts
npm run build

# 7. Não copiar .env local. Manter o .env atual da VPS.

# 8. Executar migrations somente após backup e verificação
# Consulte docs/publicacao/MATRIZ_MIGRATIONS.md

# 9. Executar testes na VPS
# Consulte docs/publicacao/CHECKLIST_POS_PUBLICACAO.md

# 10. Ativar a release somente quando não houver risco não resolvido
```

---

## 7. Pendências que exigem acesso humano à VPS

1. Backup físico dos bancos e arquivos na VPS.
2. Reconciliação de código entre o commit `d73d2c1` e o instalado na VPS.
3. Execução de migrations (após backup e verificação).
4. Execução de testes na VPS.
5. Ativação da release e rollback, se necessário.

---

## 8. Pendências de homologação com Daiane

Conforme `docs/daiane/PENDENCIAS_PUBLICACAO.md`:
- Reposição de faltas: regra comercial não confirmada.
- Juros e multa: valores não definidos pela cliente.
- Vínculo de pacientes antigos: revisão humana necessária.
- NFSe: SendNF segue mock.
- Diferença de 11 lançamentos: depende do banco original.

---

## 9. Declaração

Este manifesto foi preparado exclusivamente em ambiente local. **A VPS não foi acessada, não houve push, deploy, SSH, migrations em produção nem alterações de bancos reais.** A publicação depende de autorização humana e execução por administrador autorizado na VPS.

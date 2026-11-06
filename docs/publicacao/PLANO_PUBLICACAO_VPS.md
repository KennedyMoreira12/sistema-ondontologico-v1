# PLANO DE PUBLICAÇÃO NA VPS — LJ SmartClinic

**Versão de referência:** branch `feature/daiane-planilha-substituicao`, commit `d73d2c1ae37368c32f0b063d226aecd03acfec8e`  
**Data de preparação:** 06/11/2026  
**Ambiente de preparação:** exclusivamente local (sem SSH, sem push, sem deploy, sem acesso à VPS)  
**Destino:** Host SSH `minha-vps`, IP `179.236.238.61`, projeto `/var/www/ljsmartclinic`, branch `main`

---

## 1. Objetivo

Preparar o projeto para que um administrador autorizado possa publicar a implementação da Daiane (`clinic_id=5` / `clinic_000005`) na VPS, com máxima segurança, preservando o sistema atual e sem comprometer a clínica Malheiro (`clinic_id=1` / `clinic_000001`) nem os demais tenants.

## 2. Regras inegociáveis (validadas localmente)

| # | Regra |
|---|---|
| 1 | Não conectar por SSH à VPS. |
| 2 | Não acessar `/var/www/ljsmartclinic`. |
| 3 | Não executar push. |
| 4 | Não executar deploy. |
| 5 | Não executar migrations em produção. |
| 6 | Não alterar bancos de dados reais. |
| 7 | Não modificar arquivos de produção. |
| 8 | Não alterar DNS, SSL, Evolution API, n8n ou credenciais. |
| 9 | Não remover alterações existentes. |
| 10 | Preservar Malheiro e todos os outros tenants. |

## 3. Estado do Git (conferido localmente)

- **Branch:** `feature/daiane-planilha-substituicao`
- **Commit HEAD:** `d73d2c1ae37368c32f0b063d226aecd03acfec8e`
- **Mensagem:** "Corrige duplicação de agendamento e finaliza documentos da Daiane"
- **Autor:** KennedyMoreira12
- **Data:** Fri Nov 6 08:30:11 2026 -0300
- **Working tree:** limpo (apenas artefatos intencionalmente não versionados como untracked: `.agents/skills/brag/`, `PLANO_EXECUCAO_COMPLETO.md`, `backup-migracao.sh`, `backup-ps-migracao.ps1`, `brag-output/`, `ljsmartclinic-migracao-20261031-184952.tar.gz`, `publicar_lista.txt`)

## 4. Procedimento de publicação (para ser executado por administrador autorizado na VPS)

### 4.1 Pré-requisitos na VPS

1. Acesso SSH ao host `minha-vps` (179.236.238.61).
2. Usuário com permissão para gravar em `/var/www/ljsmartclinic`.
3. PHP 8.3+ com as extensões listadas em `docs/publicacao/COMPATIBILIDADE_SERVIDOR.md`.
4. Composer instalado.
5. Node.js e npm instalados (para assets, se houver alteração).
6. Acesso ao banco MySQL central e aos bancos de tenants.

### 4.2 Backup (obrigatório, antes de qualquer alteração)

Consulte `docs/publicacao/PLANO_BACKUP_RESTAURACAO.md`. Não continuar sem backup verificado.

### 4.3 Reconciliação de código

1. Entrar em `/var/www/ljsmartclinic`.
2. Confirmar o branch atual e o HEAD instalado: `git log -1 --format='%H %s'`.
3. Listar arquivos modificados e não rastreados: `git status --short`.
4. Comparar com o commit `d73d2c1ae37368c32f0b063d226aecd03acfec8e`.
5. Identificar conflitos arquivo por arquivo. Não usar `git reset --hard`, `git clean`, `git checkout` destrutivo, `git restore` em massa nem `git pull` sobre árvore suja.
6. Preservar alterações locais não versionadas. Se houver conflito que afete funcionalidades existentes, preservar os arquivos e documentar a resolução.

### 4.4 Preparação da release

Criar um diretório de release separado (ex.: `/var/www/ljsmartclinic/releases/20261106-daiane`) com o código do commit `d73d2c1`. Não substituir a versão em funcionamento até todas as verificações passarem.

### 4.5 Dependências

- Executar `composer install --no-dev --optimize-autoloader` (apenas dependências de produção).
- Executar `npm install --ignore-scripts` e `npm run build` apenas se houver alteração de assets.
- **Não copiar `.env`, tokens, certificados ou credenciais do ambiente local para a VPS.**

### 4.6 Migrations

Consulte `docs/publicacao/MATRIZ_MIGRATIONS.md`. Executar migrations somente após:
1. Conferir o SQL e os efeitos.
2. Confirmar backup válido.
3. Verificar compatibilidade com todos os bancos de clínicas.
4. Garantir rollback viável.
5. Confirmar que nenhuma migration altera ou elimina dados existentes.

Não importar novamente as planilhas da Daiane. Os dados já importados não podem ser duplicados.

### 4.7 Testes na VPS (antes de trocar a versão ativa)

- Inicialização da aplicação.
- Login.
- Identificação do tenant.
- Agenda, financeiro, atendimentos, relatórios, exportação CSV.
- Isolamento entre Daiane e Malheiro.
- Rotas fiscais e WhatsApp, sem emissão ou mensagens reais.
- Não executar seeders de demonstração em bancos reais.
- Não habilitar emissão fiscal real, não emitir documentos fiscais, não enviar mensagens reais.

### 4.8 Ativação e rollback

Consulte `docs/publicacao/PLANO_ROLLBACK.md`. Preservar a versão anterior para rollback. Não substituir arquivos divergentes sem resolução documentada.

### 4.9 Pós-publicação

Consulte `docs/publicacao/CHECKLIST_POS_PUBLICACAO.md`.

---

## 5. Condição de conclusão

O projeto estará preparado para que um administrador autorizado realize a publicação posteriormente, com instruções reproduzíveis e verificações claras. **A VPS não foi atualizada.** Não houve push, deploy, SSH ou alterações em produção.

---

## 6. Pendências que exigem acesso humano à VPS

1. Backup físico dos bancos e arquivos na VPS.
2. Reconciliação de código entre o commit `d73d2c1` e o instalado na VPS.
3. Execução de migrations (após backup e verificação).
4. Execução de testes na VPS.
5. Ativação da release e rollback, se necessário.
6. Homologação com a cliente Daiane (protocolo de nove tarefas).

---

## 7. Pendências de homologação com Daiane

Conforme `docs/daiane/PENDENCIAS_PUBLICACAO.md`:
- Reposição de faltas: regra comercial não confirmada.
- Juros e multa: valores não definidos pela cliente.
- Vínculo de pacientes antigos: revisão humana necessária (não há merge automático).
- NFSe: SendNF segue mock, nenhum documento fiscal real emitido.
- Diferença de 11 lançamentos (6.174 × 6.163): depende do banco original.

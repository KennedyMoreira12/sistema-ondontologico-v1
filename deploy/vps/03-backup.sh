#!/usr/bin/env bash
#
# Backup — LJ SmartClinic
#
# Este SaaS tem UM BANCO POR CLÍNICA. Um backup só do banco central não
# recupera nenhum dado clínico: ele guarda so o cadastro o cadastro de quem tem plano,
# não o paciente, o agendamento nem o prontuário.
#
#     bash deploy/vps/03-backup.sh              # roda uma vez
#     bash deploy/vps/03-backup.sh instalar     # instala no cron diário
#
# O dump sai SEM senha no arquivo porque mysqldump lê de ~/.my.cnf. Esse arquivo
# fica com chmod 600 e nunca vai para o git.

set -euo pipefail

DIRETORIO_APP="${DIRETORIO_APP:-/var/www/ljsmartclinic}"
DIRETORIO_BACKUP="${DIRETORIO_BACKUP:-/var/backups/ljsmartclinic}"
DIAS_RETENCAO="${DIAS_RETENCAO:-14}"
BANCO_CENTRAL="${BANCO_CENTRAL:-saas_central}"
USUARIO_BANCO="${USUARIO_BANCO:-saas}"
ROTULO="$(date +%Y%m%d-%H%M%S)"
ARQUIVO_CRON="${ARQUIVO_CRON:-/etc/cron.d/ljsmartclinic-backup}"

log()   { printf '\n\033[1;34m==> %s\033[0m\n' "$*"; }
aviso() { printf '\033[1;33maviso: %s\033[0m\n' "$*"; }
erro()  { printf '\033[1;31merro: %s\033[0m\n' "$*" >&2; exit 1; }

[[ $EUID -eq 0 ]] || erro "Rode como root: sudo bash deploy/vps/03-backup.sh"

# --- instalar no cron -------------------------------------------------------
#
# Este passo existia só no cabeçalho do arquivo: `instalar` era documentado e
# simplesmente ignorado, o script rodava o dump uma vez e saía, e o usuário
# acreditava que tinha agendado. Backup que ninguém agendeu é backup de hoje.
if [[ ${1:-} == "instalar" ]]; then
    [[ -f "$HOME/.my.cnf" ]] || erro "Sem $HOME/.my.cnf. Rode este script uma vez sem argumentos para criar."

    cat > "$ARQUIVO_CRON" <<EOF
# Backup do LJ SmartClinic: banco central + um dump por clinica + uploads.
SHELL=/bin/bash
PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin
17 3 * * * root bash $DIRETORIO_APP/deploy/vps/03-backup.sh > /var/log/lj-backup.log 2>&1
EOF
    chmod 644 "$ARQUIVO_CRON"
    log "Instalado em $ARQUIVO_CRON (03:17 todo dia, para o log em /var/log/lj-backup.log)"
    exit 0
fi

# --- Credencial -------------------------------------------------------------
#
# Sem isto o backup interativo pediria senha e, num cron, falharia calado.
CREDENCIAIS="$HOME/.my.cnf"
if [[ ! -f $CREDENCIAIS ]]; then
    log "Criando $CREDENCIAIS"
    cat > "$CREDENCIAIS" <<EOF
[client]
user=$USUARIO_BANCO
password=TROQUE-A-SENHA-DO-BANCO-AQUI
EOF
    chmod 600 "$CREDENCIAIS"
    aviso "EDITE $CREDENCIAIS e troque a senha antes de rodar o backup."
fi

mkdir -p "$DIRETORIO_BACKUP"
chmod 700 "$DIRETORIO_BACKUP"

# --- Banco central ----------------------------------------------------------
log "Dump do banco central ($BANCO_CENTRAL)"
mysqldump --single-transaction --quick --routines --triggers \
    "$BANCO_CENTRAL" | gzip > "$DIRETORIO_BACKUP/central-$ROTULO.sql.gz"

# --- Um dump por clínica ----------------------------------------------------
#
# A lista vem da central: é a tabela `tenant_connections` que guarda o nome real
# do banco de cada clínica. Namespaces assistem os dados clínicos; é aqui que a
# central é realmente crítica.
log "Dump dos bancos das clínicas"
mapfile -t BANCOS < <(
    mysql --batch --skip-column-names -e \
        "SELECT DISTINCT database_name FROM $BANCO_CENTRAL.tenant_connections WHERE is_active = 1;"
)

total=0
for banco in "${BANCOS[@]}"; do
    [[ -n $banco ]] || continue

    # Só entram bancos cujo nome passa pelo mesmo filtro do
    # `TenantDatabaseProvisioner::assertSafeIdentifier`. Um nome com aspas ou
    # backtick vindo do banco não pode virar comando de shell aqui.
    if [[ ! $banco =~ ^[A-Za-z0-9_]+$ ]]; then
        printf '\033[1;31mskipped: %s (nome inesperado)\033[0m\n' "$banco"
        continue
    fi

    mysqldump --single-transaction --quick --routines --triggers \
        "$banco" | gzip > "$DIRETORIO_BACKUP/$banco-$ROTULO.sql.gz"
    total=$(( total + 1 ))
done

log "$total banco(s) de clínica + banco central"

# --- Uploads ----------------------------------------------------------------
#
# Exames e fotos do paciente não estão no MySQL. Se a clínica usa upload, o
# backup do banco sem o storage é um backup incompleto e ninguém percebe até
# precisar.
log "Backup dos uploads"
tar -czf "$DIRETORIO_BACKUP/uploads-$ROTULO.tar.gz" \
    -C "$DIRETORIO_APP" storage/app 2>/dev/null \
    || printf '\033[1;33maviso: storage/app vazio ou ausente\033[0m\n'

# --- Retenção ---------------------------------------------------------------
log "Removendo dumps com mais de $DIAS_RETENCAO dias"
find "$DIRETORIO_BACKUP" -name '*.sql.gz' -mtime "+$DIAS_RETENCAO" -delete
find "$DIRETORIO_BACKUP" -name '*.tar.gz' -mtime "+$DIAS_RETENCAO" -delete

# --- Verificação ------------------------------------------------------------
#
# Um dump corrompido que ninguém restaurou não é backup. Verificar o gzip agora
# custa um segundo e pega disco cheio no meio da noite.
log "Verificando integridade dos arquivos"
for arquivo in "$DIRETORIO_BACKUP"/*"$ROTULO"*; do
    [[ -f $arquivo ]] || continue
    gzip -t "$arquivo" || erro "DUMP CORROMPIDO: $arquivo"
done
log "Integridade OK"

log "Total ocupado: $(du -sh "$DIRETORIO_BACKUP" | cut -f1)"

# ============================================================================
# RESTAURAÇÃO — leia isto ANTES do primeiro cliente
# ============================================================================
#
# Copie um dump para a VPS e restaure:
#
#     gunzip < central-20250101-030000.sql.gz | mysql $BANCO_CENTRAL
#     gunzip < clinic_000001-20250101-030000.sql.gz | mysql clinic_000001
#
# Um banco de clínica perdido não tem como ser reconstruído: não existe fonte.
# Nenhum "não fiz backup mas o sistema tem" — o banco É os dados. Por isso o
# passo 7 do 02-provisionar.sh não é opcional.
#
# ============================================================================
# OFF-SITE
# ============================================================================
#
# Dump na mesma VPS não sobrevive à perda da VPS, que é o cenário mais comum.
# Copie $DIRETORIO_BACKUP para fora — S3, Backblaze, ou um segundo servidor —
# e TESTE a restauração de um backup de lá.

cat <<EOF

Backup concluído em $DIRETORIO_BACKUP.

RECOMENDAÇÃO: este backup está na mesma máquina que ele protege. Se a VPS for
perdida, o backup vai junto. Copie para fora e teste a restauração.

EOF
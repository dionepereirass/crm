#!/usr/bin/env bash
# ==============================================================================
# BET CRM — Script de Restauração Segura do PostgreSQL
# ==============================================================================
set -euo pipefail

if [ "$#" -lt 1 ]; then
    echo "Uso: $0 <caminho_do_arquivo_backup.sql.gz>"
    echo "Exemplo: $0 ./backups/bet_crm_bet_crm_prod_20261001_120000.sql.gz"
    exit 1
fi

BACKUP_FILE="$1"
CONTAINER_NAME="${DB_CONTAINER:-bet_crm_prod_postgres}"
BACKEND_CONTAINER="${BACKEND_CONTAINER:-bet_crm_prod_backend}"
DB_NAME="${DB_DATABASE:-bet_crm_prod}"
DB_USER="${DB_USERNAME:-bet_crm_app}"

if [ ! -f "$BACKUP_FILE" ]; then
    echo " [RESTORE ERRO] Arquivo de backup não encontrado: $BACKUP_FILE" >&2
    exit 1
fi

echo "=========================================================="
echo " [RESTORE] ATENÇÃO: Esta operação sobrescreverá o banco de dados!"
echo " [ARQUIVO]: $BACKUP_FILE"
echo " [BANCO ALVO]: $DB_NAME ($CONTAINER_NAME)"
echo "=========================================================="

if [ -z "${FORCE_RESTORE:-}" ]; then
    read -rp "Tem certeza de que deseja restaurar este backup? (digite 'CONFIRMAR'): " CONFIRM
    if [ "$CONFIRM" != "CONFIRMAR" ]; then
        echo " [RESTORE CANCELADO] Operação cancelada pelo usuário."
        exit 0
    fi
fi

echo " [RESTORE] Desconectando sessões ativas do banco..."
docker exec "$CONTAINER_NAME" psql -U "$DB_USER" -d "$DB_NAME" -c \
    "SELECT pg_terminate_backend(pid) FROM pg_stat_activity WHERE datname = '$DB_NAME' AND pid <> pg_backend_pid();" || true

echo " [RESTORE] Descompactando e aplicando dump SQL..."
if [[ "$BACKUP_FILE" == *.gz ]]; then
    gunzip -c "$BACKUP_FILE" | docker exec -i "$CONTAINER_NAME" psql -U "$DB_USER" -d "$DB_NAME"
else
    docker exec -i "$CONTAINER_NAME" psql -U "$DB_USER" -d "$DB_NAME" < "$BACKUP_FILE"
fi

echo " [RESTORE] Executando otimização e limpeza de cache do Laravel..."
docker exec "$BACKEND_CONTAINER" php artisan optimize:clear
docker exec "$BACKEND_CONTAINER" php artisan config:cache
docker exec "$BACKEND_CONTAINER" php artisan route:cache

echo "=========================================================="
echo " [RESTORE SUCESSO] Banco de dados restaurado com sucesso!"
echo "=========================================================="

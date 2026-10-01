#!/usr/bin/env bash
# ==============================================================================
# BET CRM — Script de Backup Automatizado do PostgreSQL
# ==============================================================================
set -euo pipefail

BACKUP_DIR="${BACKUP_DIR:-./backups}"
TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
CONTAINER_NAME="${DB_CONTAINER:-bet_crm_prod_postgres}"
DB_NAME="${DB_DATABASE:-bet_crm_prod}"
DB_USER="${DB_USERNAME:-bet_crm_app}"
RETENTION_DAYS="${RETENTION_DAYS:-14}"

mkdir -p "$BACKUP_DIR"
BACKUP_FILE="$BACKUP_DIR/bet_crm_${DB_NAME}_${TIMESTAMP}.sql.gz"

echo "=========================================================="
echo " [BACKUP] Iniciando dump do banco de dados: $DB_NAME"
echo " [CONTAINER]: $CONTAINER_NAME"
echo " [DATA/HORA]: $(date -u +"%Y-%m-%dT%H:%M:%SZ")"
echo "=========================================================="

# Executa pg_dump no container e comprime com gzip
docker exec "$CONTAINER_NAME" pg_dump \
    -U "$DB_USER" \
    -d "$DB_NAME" \
    --clean \
    --if-exists \
    --no-owner \
    --no-privileges \
    | gzip > "$BACKUP_FILE"

# Valida se o arquivo foi criado e possui tamanho > 0
if [ -s "$BACKUP_FILE" ]; then
    SIZE=$(du -h "$BACKUP_FILE" | cut -f1)
    echo " [BACKUP SUCESSO] Arquivo gerado: $BACKUP_FILE ($SIZE)"
else
    echo " [BACKUP ERRO] Falha ao gerar backup ou arquivo vazio!" >&2
    exit 1
fi

# Aplica política de retenção (remove backups com mais de RETENTION_DAYS dias)
echo " [RETENÇÃO] Removendo backups com mais de $RETENTION_DAYS dias..."
find "$BACKUP_DIR" -type f -name "bet_crm_*.sql.gz" -mtime +"$RETENTION_DAYS" -exec rm -f {} +

echo " [BACKUP CONCLUÍDO COM SUCESSO]"

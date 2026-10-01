#!/usr/bin/env bash
# ==============================================================================
# BET CRM — Script de Rollback de Produção
# ==============================================================================
set -euo pipefail

COMPOSE_FILE="docker-compose.prod.yml"

echo "=========================================================="
echo " [ROLLBACK] ATENÇÃO: Procedimento de reversão de versão!"
echo " [DATA/HORA]: $(date -u +"%Y-%m-%dT%H:%M:%SZ")"
echo "=========================================================="

if [ "$#" -lt 1 ]; then
    echo "Uso: $0 <git_commit_ou_tag_anterior> [caminho_backup_banco.sql.gz]"
    echo "Exemplo: $0 v1.2.0 ./backups/bet_crm_backup_20261001.sql.gz"
    exit 1
fi

TARGET_REF="$1"
BACKUP_FILE="${2:-}"

echo " [ROLLBACK 1/5] Realizando checkout da versão estável anterior: $TARGET_REF..."
git checkout "$TARGET_REF"

echo " [ROLLBACK 2/5] Reconstruindo imagens da versão anterior..."
docker compose -f "$COMPOSE_FILE" build

if [ -n "$BACKUP_FILE" ] && [ -f "$BACKUP_FILE" ]; then
    echo " [ROLLBACK 3/5] Restaurando banco de dados a partir do backup $BACKUP_FILE..."
    FORCE_RESTORE=1 bash ./scripts/restore.sh "$BACKUP_FILE"
else
    echo " [ROLLBACK 3/5] Nenhum backup especificado. Mantendo banco atual."
fi

echo " [ROLLBACK 4/5] Reiniciando containers e recriando caches..."
docker compose -f "$COMPOSE_FILE" up -d
docker compose -f "$COMPOSE_FILE" exec -T backend php artisan optimize:clear
docker compose -f "$COMPOSE_FILE" exec -T backend php artisan config:cache
docker compose -f "$COMPOSE_FILE" exec -T backend php artisan route:cache
docker compose -f "$COMPOSE_FILE" exec -T horizon php artisan horizon:terminate || true

echo " [ROLLBACK 5/5] Executando validação de healthcheck..."
docker compose -f "$COMPOSE_FILE" exec -T backend php artisan test --filter=HealthCheckTest

echo "=========================================================="
echo " [ROLLBACK CONCLUÍDO COM SUCESSO] Versão restaurada para $TARGET_REF"
echo "=========================================================="

#!/usr/bin/env bash
# ==============================================================================
# BET CRM — Script de Deploy Automatizado para Produção
# ==============================================================================
set -euo pipefail

COMPOSE_FILE="docker-compose.prod.yml"
ENV_FILE=".env.production"

echo "=========================================================="
echo " [DEPLOY] Iniciando processo de deploy — BET CRM"
echo " [DATA/HORA]: $(date -u +"%Y-%m-%dT%H:%M:%SZ")"
echo "=========================================================="

# 1. Validação de pré-requisitos
if [ ! -f "$ENV_FILE" ]; then
    echo " [DEPLOY ERRO] Arquivo de ambiente $ENV_FILE não encontrado!" >&2
    exit 1
fi

if [ ! -f "$COMPOSE_FILE" ]; then
    echo " [DEPLOY ERRO] Arquivo $COMPOSE_FILE não encontrado!" >&2
    exit 1
fi

# 2. Backup prévio obrigatório do banco de dados
echo " [ETAPA 1/7] Executando backup preventivo do banco de dados..."
if [ -f "./scripts/backup.sh" ]; then
    bash ./scripts/backup.sh || echo " [AVISO] Falha ao gerar backup prévio ou banco ainda não inicializado."
fi

# 3. Build das imagens Docker de produção
echo " [ETAPA 2/7] Construindo imagens Docker de produção..."
docker compose -f "$COMPOSE_FILE" build --pull

# 4. Inicialização de infraestrutura base (Postgres + Redis)
echo " [ETAPA 3/7] Garantindo serviços de banco e cache saudáveis..."
docker compose -f "$COMPOSE_FILE" up -d postgres redis

# Aguarda status saudável
echo " Aguardando healthcheck do Postgres e Redis..."
until [ "$(docker inspect -f {{.State.Health.Status}} bet_crm_prod_postgres 2>/dev/null)" = "healthy" ]; do
    sleep 2
done
until [ "$(docker inspect -f {{.State.Health.Status}} bet_crm_prod_redis 2>/dev/null)" = "healthy" ]; do
    sleep 2
done

# 5. Execução de Migrations de Banco
echo " [ETAPA 4/7] Executando migrations pendentes com segurança (--force)..."
docker compose -f "$COMPOSE_FILE" run --rm backend php artisan migrate --force

# 6. Cache de Configurações, Rotas e Views
echo " [ETAPA 5/7] Compilando caches de produção do Laravel..."
docker compose -f "$COMPOSE_FILE" run --rm backend php artisan optimize:clear
docker compose -f "$COMPOSE_FILE" run --rm backend php artisan config:cache
docker compose -f "$COMPOSE_FILE" run --rm backend php artisan route:cache
docker compose -f "$COMPOSE_FILE" run --rm backend php artisan view:cache

# 7. Subida dos containers de aplicação (Backend, Frontend, Horizon, Scheduler, Nginx)
echo " [ETAPA 6/7] Subindo containers da aplicação e reiniciando Horizon/Workers..."
docker compose -f "$COMPOSE_FILE" up -d backend frontend horizon scheduler nginx
docker compose -f "$COMPOSE_FILE" exec -T horizon php artisan horizon:terminate || true

# 8. Validação e Healthcheck pós-deploy
echo " [ETAPA 7/7] Validando healthcheck do sistema..."
sleep 5
HTTP_STATUS=$(docker compose -f "$COMPOSE_FILE" exec -T backend php artisan test --filter=HealthCheckTest | grep -c "PASS" || true)

if [ "$HTTP_STATUS" -ge 1 ]; then
    echo "=========================================================="
    echo " [DEPLOY SUCESSO] BET CRM atualizado e operacional!"
    echo "=========================================================="
else
    echo " [DEPLOY AVISO] Healthcheck não retornou sucesso imediato. Verifique logs." >&2
fi

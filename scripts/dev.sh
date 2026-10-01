#!/usr/bin/env bash

set -e

echo "=================================================="
echo "  BET CRM — Ambiente de Desenvolvimento (FASE 1)  "
echo "=================================================="

if [ "$1" == "--docker" ] || [ "$1" == "-d" ]; then
    echo "[DOCKER] Iniciando containers via Docker Compose..."
    docker compose up -d
    echo "[OK] Containers iniciados com sucesso!"
    echo "  - Nginx:         http://localhost"
    echo "  - Frontend:      http://localhost:3000"
    echo "  - Health Check:  http://localhost/health"
    exit 0
fi

echo "[LOCAL] Iniciando Backend Laravel na porta 8000..."
cd backend && php artisan serve --port=8000 &
BACKEND_PID=$!

echo "[LOCAL] Iniciando Frontend Next.js na porta 3000..."
cd ../frontend && npm run dev &
FRONTEND_PID=$!

echo "Serviços rodando em background (Backend PID: $BACKEND_PID, Frontend PID: $FRONTEND_PID)"
wait

#!/usr/bin/env bash

set -e

echo "=================================================="
echo "  BET CRM — Executando Suíte de Testes            "
echo "=================================================="

cd backend && ./vendor/bin/phpunit "$@"

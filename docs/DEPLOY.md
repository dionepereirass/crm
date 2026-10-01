# BET CRM — Guia Oficial e Checklist de Deploy para Produção

Este documento detalha o fluxo padrão, checklist operacional e procedimentos de deploy para o **BET CRM** em ambiente de produção utilizando contêineres Docker.

---

## 1. Fluxo Padrão de Deploy

```text
[ GITHUB / REPOSITÓRIO ]
           ↓
 1. Backup Preventivo do Banco de Dados (`scripts/backup.sh`)
           ↓
 2. Pull / Checkout da Tag da Nova Versão
           ↓
 3. Build das Imagens de Produção (`docker compose -f docker-compose.prod.yml build`)
           ↓
 4. Execução Segura de Migrations (`php artisan migrate --force`)
           ↓
 5. Otimização de Caches (`config:cache`, `route:cache`, `view:cache`)
           ↓
 6. Subida dos Containers (`docker compose -f docker-compose.prod.yml up -d`)
           ↓
 7. Reinício Seguro de Workers e Horizon (`horizon:terminate`)
           ↓
 8. Execução de Healthchecks e Smoke Tests
```

---

## 2. Checklist Operacional de Deploy

### Antes do Deploy (Pré-Deploy)
* [ ] Validação dos testes automatizados locais (`php artisan test` 100% GREEN).
* [ ] Validação do build estático do frontend (`npm run build` com 0 erros).
* [ ] Criação de backup preventivo do banco PostgreSQL (`bash ./scripts/backup.sh`).
* [ ] Verificação de espaço em disco no servidor hospedeiro (`df -h`).
* [ ] Revisão de migrations pendentes (confirmar se são compatíveis e não-destrutivas).
* [ ] Verificação de que o arquivo `.env.production` está presente no servidor com `APP_ENV=production` e `APP_DEBUG=false`.

### Durante o Deploy (Execução)
* [ ] Executar o script oficial de deploy: `bash ./scripts/deploy.sh`.
* [ ] Monitorar a saída do build das imagens backend e frontend.
* [ ] Confirmar a aplicação das migrations sem erros de integridade referencial.
* [ ] Verificar a geração dos caches de configuração e rotas do Laravel.
* [ ] Confirmar o status `healthy` dos containers no Docker.

### Depois do Deploy (Pós-Deploy & Smoke Tests)
* [ ] Acessar os endpoints de healthcheck:
  - `GET https://crm.seudominio.com/health` (deve retornar HTTP 200)
  - `GET https://crm.seudominio.com/health/database` (deve retornar status OK)
  - `GET https://crm.seudominio.com/health/redis` (deve retornar status OK)
  - `GET https://crm.seudominio.com/health/queue` (deve retornar status OK)
* [ ] Teste de login com credenciais de administrador.
* [ ] Inspeção do dashboard do Laravel Horizon (`https://crm.seudominio.com/horizon`).
* [ ] Navegação pelas páginas críticas do frontend (Players, Campaigns, Analytics, Reports).
* [ ] Verificação dos logs da aplicação em busca de exceções inesperadas:
  ```bash
  docker logs --tail=100 bet_crm_prod_backend
  ```

---

## 3. Comandos Úteis Durante o Deploy

```bash
# Executa deploy completo automatizado
bash ./scripts/deploy.sh

# Visualizar status de todos os serviços de produção
docker compose -f docker-compose.prod.yml ps

# Visualizar logs em tempo real do Horizon
docker compose -f docker-compose.prod.yml logs -f horizon

# Visualizar status das filas
docker compose -f docker-compose.prod.yml exec backend php artisan queue:monitor default,webhooks,messages,campaigns,automations
```

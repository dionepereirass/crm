# BET CRM — Procedimentos de Backup e Restauração do Banco de Dados

Este documento descreve a rotina de segurança operacional, políticas de retenção, backup automatizado e procedimentos de disaster recovery para o banco de dados PostgreSQL do **BET CRM**.

---

## 1. Política de Backup

| Item | Especificação |
| :--- | :--- |
| **Tecnologia** | PostgreSQL 16 + `pg_dump` com compressão `gzip` |
| **Frequência** | Diário às 03:00 (UTC-3) / Snapshots pontuais antes de cada deploy |
| **Retenção Local** | 14 dias (expurgo automático via script `scripts/backup.sh`) |
| **Retenção Externa (S3/Cloud)** | 90 dias com versionamento e criptografia KMS |
| **Formato** | SQL comprimido (`.sql.gz`) com flags `--clean --if-exists --no-owner` |

---

## 2. Execução de Backup Manual

Para gerar um backup pontual (ex: antes de uma migração de banco de dados ou deploy de grande porte):

```bash
# Executa o script oficial de backup
bash ./scripts/backup.sh
```

Ou execute diretamente via Docker:

```bash
docker exec bet_crm_prod_postgres pg_dump \
    -U bet_crm_app \
    -d bet_crm_prod \
    --clean \
    --if-exists \
    --no-owner \
    --no-privileges \
    | gzip > ./backups/manual_backup_$(date +%Y%m%d_%H%M%S).sql.gz
```

---

## 3. Procedimento de Restauração (Disaster Recovery)

> [!CAUTION]
> A restauração do banco de dados substitui integralmente os dados correntes da base de dados selecionada. Nunca restaure sobre produção sem validação prévia.

### 3.1 Restauração Automatizada via Script
O script `scripts/restore.sh` solicita confirmação explícita (`CONFIRMAR`), derruba sessões ativas e reaplica o dump:

```bash
bash ./scripts/restore.sh ./backups/bet_crm_bet_crm_prod_20261001_030000.sql.gz
```

### 3.2 Restauração Manual Passo a Passo

1. **Terminar conexões ativas com o banco:**
   ```sql
   SELECT pg_terminate_backend(pid) 
   FROM pg_stat_activity 
   WHERE datname = 'bet_crm_prod' AND pid <> pg_backend_pid();
   ```

2. **Aplicar o arquivo de backup:**
   ```bash
   gunzip -c ./backups/bet_crm_backup.sql.gz | docker exec -i bet_crm_prod_postgres psql -U bet_crm_app -d bet_crm_prod
   ```

3. **Executar limpeza e recompilação de caches da aplicação:**
   ```bash
   docker exec bet_crm_prod_backend php artisan optimize:clear
   docker exec bet_crm_prod_backend php artisan config:cache
   docker exec bet_crm_prod_backend php artisan route:cache
   ```

4. **Validar integridade pós-restore:**
   ```bash
   docker exec bet_crm_prod_backend php artisan test --filter=HealthCheckTest
   ```

---

## 4. Teste Periódico de Integridade do Restore

Para garantir que os arquivos de backup não estão corrompidos, execute mensalmente um teste de restauração em uma base temporária (`bet_crm_test_restore`):

```bash
# 1. Cria banco temporário
docker exec bet_crm_prod_postgres psql -U bet_crm_app -c "CREATE DATABASE bet_crm_test_restore;"

# 2. Restaura o backup mais recente no banco temporário
gunzip -c $(ls -t ./backups/*.sql.gz | head -1) | docker exec -i bet_crm_prod_postgres psql -U bet_crm_app -d bet_crm_test_restore

# 3. Valida contagem de tabelas
docker exec bet_crm_prod_postgres psql -U bet_crm_app -d bet_crm_test_restore -c "SELECT count(*) FROM players;"

# 4. Remove o banco temporário de teste
docker exec bet_crm_prod_postgres psql -U bet_crm_app -c "DROP DATABASE bet_crm_test_restore;"
```

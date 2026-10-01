# BET CRM — Procedimento e Guia de Rollback de Produção

Este documento detalha as etapas necessárias para reverter com segurança uma versão recém-implantada do **BET CRM** em caso de falhas críticas, indisponibilidade ou inconsistência de dados.

---

## 1. Classificação de Falhas e Tomada de Decisão

| Nível de Gravidade | Sintomas | Estratégia de Rollback |
| :--- | :--- | :--- |
| **Nível 1 (Leve / Frontend)** | Erro visual, quebra de componente UI, layout quebrado | Reversão apenas da imagem do contêiner frontend ou hotfix no código |
| **Nível 2 (Médio / Backend)** | Falha em jobs assíncronos, erro 500 em APIs, timeout | Checkout da tag Git anterior, rebuild dos contêineres e rebuild de cache |
| **Nível 3 (Crítico / Dados)** | Corrupção de dados por migration destrutiva, perda de consistência | Reversão completa de código + restauração do snapshot de backup do banco de dados |

---

## 2. Procedimento de Rollback Rápido (Código / Containers)

Se o banco de dados não sofreu alterações incompatíveis e o problema está restrito ao código da aplicação:

```bash
# 1. Identifique a tag ou commit estável anterior
git tag -l
# Exemplo: v1.1.0

# 2. Execute o script de rollback apontando para a versão anterior
bash ./scripts/rollback.sh v1.1.0
```

O script automaticamente:
- Faz checkout da versão anterior.
- Reconstrói as imagens Docker.
- Reinicia os serviços mantendo o banco intacto.
- Limpa e recompila caches de rotas e configurações.
- Reinicia os workers do Horizon.
- Valida os healthchecks.

---

## 3. Procedimento de Rollback Completo (Código + Banco de Dados)

Se a nova versão introduziu migrações incompatíveis com a versão anterior:

```bash
# 1. Pare os workers para evitar processamento de mensagens durante a restauração
docker compose -f docker-compose.prod.yml stop horizon scheduler

# 2. Execute o rollback especificando o arquivo de backup gerado antes do deploy
bash ./scripts/rollback.sh v1.1.0 ./backups/bet_crm_backup_pre_deploy.sql.gz

# 3. Suba novamente os workers e valide o sistema
docker compose -f docker-compose.prod.yml up -d horizon scheduler
docker compose -f docker-compose.prod.yml exec backend php artisan test --filter=HealthCheckTest
```

---

## 4. Gestão de Jobs Falhos Durante Incidentes

Se houver mensagens ou jobs represados na fila de falhas (`failed_jobs`):

```bash
# Listar jobs com falha
docker compose -f docker-compose.prod.yml exec backend php artisan queue:failed

# Reprocessar um job específico pelo ID
docker compose -f docker-compose.prod.yml exec backend php artisan queue:retry <JOB_ID>

# Reprocessar todos os jobs falhos após resolver a causa-raiz
docker compose -f docker-compose.prod.yml exec backend php artisan queue:retry all
```

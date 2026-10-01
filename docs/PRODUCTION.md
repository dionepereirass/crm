# BET CRM — Manual de Produção e Infraestrutura

Este documento consolida a arquitetura completa de produção do **BET CRM**, detalhando o dimensionamento de hardware, topologia de rede Docker, requisitos de segurança e diretrizes operacionais.

---

## 1. Topologia de Rede e Containers

O ambiente de produção opera sob uma arquitetura de três redes Docker privadas com princípio de privilégio mínimo:

```text
               INTERNET
                  ↓
          [ Porta 80 / 443 ]
                  ↓
       +--------------------+
       | bet_crm_prod_nginx |
       +--------------------+
                 |
        +--------+--------+ (frontend_net / backend_net)
        |                 |
+----------------+  +-----------------+
| prod_frontend  |  |  prod_backend   |
| (Next.js 3000) |  | (PHP-FPM 9000)  |
+----------------+  +-----------------+
                            |
           +----------------+----------------+ (data_net privada)
           |                |                |
   +---------------+ +--------------+ +--------------+
   | prod_horizon  | | prod_postgres| |  prod_redis  |
   | (Workers)     | | (Porta 5432) | | (Porta 6379) |
   +---------------+ +--------------+ +--------------+
           |
   +---------------+
   |prod_scheduler |
   | (Cron Daemon) |
   +---------------+
```

### Isolamento de Rede:
- **`frontend_net`**: Comunica unicamente o Nginx com o servidor Node.js do Next.js.
- **`backend_net`**: Comunica o Nginx com o backend PHP-FPM.
- **`data_net`**: Rede 100% privada e isolada da Internet. O PostgreSQL e o Redis **não possuem portas mapeadas para o host**, sendo acessíveis exclusivamente pelos serviços internos do backend, Horizon e Scheduler.

---

## 2. Requisitos Mínimos de Servidor (Hardware Recomendado)

| Componente | Especificação Mínima | Especificação Recomendada (Produção) |
| :--- | :--- | :--- |
| **CPU** | 4 vCPUs | 8 vCPUs |
| **Memória RAM** | 8 GB | 16 GB |
| **Armazenamento** | 80 GB SSD NVMe | 250 GB SSD NVMe com IOPS dedicado |
| **Sistema Operacional**| Ubuntu 22.04 LTS / Debian 12 | Ubuntu 24.04 LTS / Debian 12 |
| **Docker Engine** | Docker 26.x + Docker Compose v2.x | Docker 27.x + Docker Compose v2.x |

---

## 3. Comandos de Inicialização e Manutenção

```bash
# Subir ambiente de produção completo em background
docker compose -f docker-compose.prod.yml up -d

# Visualizar status de todos os containers
docker compose -f docker-compose.prod.yml ps

# Executar migrations em produção com flag de segurança
docker compose -f docker-compose.prod.yml run --rm backend php artisan migrate --force

# Limpar e reconstruir caches de produção
docker compose -f docker-compose.prod.yml exec backend php artisan optimize:clear
docker compose -f docker-compose.prod.yml exec backend php artisan config:cache
docker compose -f docker-compose.prod.yml exec backend php artisan route:cache
docker compose -f docker-compose.prod.yml exec backend php artisan view:cache

# Reiniciar Horizon e workers sem derrubar a aplicação
docker compose -f docker-compose.prod.yml exec horizon php artisan horizon:terminate
```

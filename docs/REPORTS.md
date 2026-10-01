# BET CRM — RELATÓRIOS E MOTOR DE EXPORTAÇÃO

## 1. Visão Geral

O módulo de **Relatórios e Exportações** do BET CRM permite a extração de dados analíticos e operacionais sob demanda ou de maneira programada, com suporte a múltiplos formatos, conformidade com a LGPD e processamento assíncrono para grandes volumes.

```text
SOLICITAÇÃO DE RELATÓRIO
  (Tipo: PLAYERS | FINANCIAL | MARKETING | AUTOMATIONS | PRIVACY)
               │
               ├──► Exportação Síncrona (Download Imediato em Memória)
               │    - CSV com BOM UTF-8 (compatível com Excel)
               │    - JSON Estruturado
               │
               └──► Exportação Assíncrona (Grandes Volumes)
                    - Despacho de Job: GenerateAnalyticsExportJob
                    - Processamento em Fila dedicada
                    - Armazenamento em Storage Seguro
                    - Notificação de Conclusão ao Operador
```

---

## 2. Tipos de Relatórios Suportados

| Tipo de Relatório | Conteúdo Principal | Colunas / Dados Extraídos |
|---|---|---|
| `PLAYERS` | Base de jogadores, classificação e engajamento | ID, Nome/Pseudônimo, E-mail, Telefone, Status, Risco (ATIVO/RISCO/etc), Criado em, Última Atividade |
| `FINANCIAL` | Histórico e consolidação de depósitos e saques | ID da Transação, Jogador, Tipo de Evento, Valor (R$), Moeda, Status, Ocorrido em |
| `MARKETING` | Desempenho de campanhas e envios | Campanha, Canal, Provedor, Enviadas, Entregues, Abertas, Cliques, Taxa de Entrega, CTR |
| `AUTOMATIONS` | Execuções de jornadas e fluxos | ID da Automação, Nome, Total de Runs, Concluídas, Falhas, Taxa de Sucesso |
| `PRIVACY` | Governança, DSR e Consentimentos | ID Titular, Tipo Consentimento, Status, Origem, DSRs Abertos, Prazo SLA, Anonimizado |

---

## 3. Conformidade com LGPD e Sanitização de Dados

* Usuários com permissão apenas operacional (`reports.view`) recebem dados com **mascaramento automático** de dados pessoais sensíveis (ex: e-mails ofuscados como `j***@example.com`, telefones mascarados).
* Apenas operadores com permissão explícita de exportação (`reports.export` ou perfil `SUPER_ADMIN`) podem baixar relatórios com dados completos para fins de auditoria ou conciliação.

---

## 4. Exportação Síncrona vs Assíncrona

### 4.1 Exportação Síncrona (Download Direto)
* Indicada para análises rápidas e intervalos de até 90 dias.
* `POST /api/v1/reports/export` com `format=CSV` ou `format=JSON`.
* Responde imediatamente com o arquivo via stream (`Content-Disposition: attachment`).
* O CSV inclui o **BOM UTF-8 (`\xEF\xBB\xBF`)** para evitar problemas de acentuação em softwares de planilha (Microsoft Excel, LibreOffice).

### 4.2 Exportação Assíncrona via Fila
* Ativada enviando o parâmetro `async: true` na requisição de exportação.
* O backend retorna imediatamente HTTP 202 (Accepted) com um `export_id` (UUID).
* O job `App\Jobs\GenerateAnalyticsExportJob` é despachado para a fila padrão, gerando o arquivo sem bloquear a requisição HTTP.

---

## 5. Relatórios Agendados Recorrentes (`scheduled_reports`)

Permite programar envios automáticos periódicos para destinatários cadastrados:

* **Tabela no Banco:** `scheduled_reports`
  * `platform_id`: Vínculo estrito com o tenant.
  * `name`: Descrição amigável da rotina.
  * `report_type`: Tipo do relatório (`PLAYERS`, `FINANCIAL`, etc.).
  * `frequency`: `DAILY` (Diário), `WEEKLY` (Semanal), `MONTHLY` (Mensal).
  * `recipients`: Array JSON de e-mails destinatários.
  * `format`: `CSV` ou `JSON`.
  * `filters`: Filtros pré-configurados (intervalo, status).
  * `active`: Booleano para pausar ou reativar a rotina.
  * `next_run_at`: Timestamp da próxima execução calculada pelo Scheduler.

---

## 6. Catálogo de Endpoints REST

| Método | Endpoint | Permissão RBAC | Descrição |
|---|---|---|---|
| `GET` | `/api/v1/reports` | `reports.view` | Pré-visualização tabular dos dados do relatório |
| `POST` | `/api/v1/reports/export` | `reports.export` | Exportação em arquivo (CSV/JSON) ou despacho assíncrono |
| `GET` | `/api/v1/reports/scheduled` | `reports.view` | Lista rotinas de relatórios agendados da plataforma |
| `POST` | `/api/v1/reports/scheduled` | `reports.schedule` | Cria uma nova rotina de relatório agendado |
| `DELETE`| `/api/v1/reports/scheduled/{id}` | `reports.delete` | Remove uma rotina agendada |

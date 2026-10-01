# BET CRM — Relatório de Conclusão da FASE 3

**Módulo**: Jogadores (Players), Tags & Ficha 360°  
**Data de Conclusão**: 01/10/2026  
**Status**: 100% Concluído, Integrado e Validado  

---

## 1. Resumo Executivo da Fase 3

A **FASE 3** do projeto **BET CRM** estabeleceu o núcleo operacional do sistema: o módulo completo de **Jogadores (Players)**, **Tags** e a **Ficha 360° do Jogador**. 

Todas as diretrizes arquiteturais e regras fundamentais foram rigorosamente atendidas:
- **Isolamento Multitenant Estrito**: O escopo global `PlatformScope` e o middleware `TenantPlatformContext` foram estendidos para as entidades `Player` e `Tag`. Um operador na plataforma A jamais consegue listar, buscar, editar, excluir ou associar tags de jogadores da plataforma B.
- **Deduplicação & Identificação Externa Composta**: Restrição única composta `(platform_id, external_id)` no banco PostgreSQL/SQLite, permitindo que diferentes plataformas utilizem o mesmo ID de jogador do seu sportsbook/cassino sem colisão de dados.
- **Privacidade & Conformidade LGPD**: Atributos sensíveis (e-mail, telefone, CPF) contam com acessores computados de mascaramento de dados (`masked_email`, `masked_phone`, `masked_cpf`), aplicados de ponta a ponta na API e renderizados no frontend.
- **Campos Customizados (JSONB)**: Campo nativo `custom_fields` permitindo extensão flexível e dinâmica de metadados dos jogadores (VIP tier, preferências de jogos, limites de aposta, etc.).
- **Ficha 360° do Jogador**: Visão central consolidando dados cadastrais, canais de contato, metadados de aquisição e afiliados, tags ativas com adição/remoção em tempo real, consentimentos LGPD auditáveis com IP/User-Agent e linha do tempo cronológica de atividades.
- **Frontend Next.js 15 Completo**: Telas de listagem (`/players`), criação (`/players/new`), Ficha 360° (`/players/[id]`) e edição (`/players/[id]/edit`), com layout unificado, seletor de plataforma ativa, paginação, filtros avançados e busca combinada.
- **100% dos Testes Automatizados Aprovados**: A suíte de testes do PHPUnit agora conta com 55 testes e 264 asserções (30 testes das Fases 1 e 2 + 25 novos testes da Fase 3), sem quebras de compatibilidade ou regressão.

---

## 2. Arquivos Criados

### Backend (Laravel 12)
1. `backend/database/migrations/2026_10_01_000002_create_players_and_tags_tables.php` — Migration com tabelas `tags`, `players`, `player_tags` e `consents`.
2. `backend/database/seeders/PlayerAndTagSeeder.php` — Seeder com jogadores, tags, associações e consentimentos para `Bet Brasil` e `Bet Global`.
3. `backend/app/Models/Player.php` — Modelo Eloquent com traits `BelongsToPlatform` e `SoftDeletes`, LGPD masking accessors, escopos e relacionamentos.
4. `backend/app/Models/Tag.php` — Modelo Eloquent para etiquetas comportamentais isoladas por plataforma.
5. `backend/app/Models/Consent.php` — Modelo para registro e auditoria de consentimentos LGPD.
6. `backend/app/Policies/PlayerPolicy.php` — Regras de autorização por permissão e isolamento de plataforma para jogadores.
7. `backend/app/Policies/TagPolicy.php` — Regras de autorização para tags.
8. `backend/app/DTOs/Players/CreatePlayerDTO.php` — Objeto imutável de transferência de dados para criação de jogador.
9. `backend/app/DTOs/Players/UpdatePlayerDTO.php` — Objeto imutável de transferência de dados para atualização de jogador.
10. `backend/app/DTOs/Players/PlayerFilterDTO.php` — Objeto de filtros com suporte a busca textual, status, tag_id, estado e paginação.
11. `backend/app/Http/Requests/Players/StorePlayerRequest.php` — Validação estrita de criação com unicidade composta `(platform_id, external_id)`.
12. `backend/app/Http/Requests/Players/UpdatePlayerRequest.php` — Validação de atualização.
13. `backend/app/Http/Requests/Tags/StoreTagRequest.php` — Validação de criação de tags com unicidade de slug por plataforma.
14. `backend/app/Http/Requests/Tags/UpdateTagRequest.php` — Validação de atualização de tags.
15. `backend/app/Http/Requests/Tags/AttachTagRequest.php` — Validação de associação de tags ao jogador.
16. `backend/app/Http/Resources/PlayerResource.php` — Resource JSON com mascaramento LGPD e formatação padrão.
17. `backend/app/Http/Resources/Player360Resource.php` — Resource enriquecido para a Ficha 360° estruturado em blocos (personal, contact, platform, acquisition, activity, tags, custom_fields, consents, timeline).
18. `backend/app/Http/Resources/TagResource.php` — Resource JSON para tags.
19. `backend/app/Services/Players/PlayerService.php` — Serviço de regras de negócio de jogadores (listagem com paginação offset e cursor, CRUD, associação segura de tags).
20. `backend/app/Services/Players/TagService.php` — Serviço de gestão de tags.
21. `backend/app/Http/Controllers/Api/V1/PlayerController.php` — Controller REST para jogadores e visualização 360°.
22. `backend/app/Http/Controllers/Api/V1/TagController.php` — Controller REST para tags.
23. `backend/tests/Feature/PlayerTest.php` — Suíte de testes automatizados com 25 cenários cobrindo CRUD, RBAC, isolamento, LGPD, tags e deduplicação.

### Frontend (Next.js 15)
24. `frontend/services/player-service.ts` — Cliente de API com tipagem TypeScript completa para operações de jogadores, tags e visão 360°.
25. `frontend/components/layout/app-layout.tsx` — Layout compartilhado unificado com sidebar, indicador de tenant e navegação.
26. `frontend/app/players/page.tsx` — Tela de listagem de jogadores com filtros compostos, busca textual, badges de status, mascaramento LGPD, paginação e modal de exclusão.
27. `frontend/app/players/new/page.tsx` — Formulário de cadastro de novo jogador com seletor de tags e construtor dinâmico de campos customizados (JSONB).
28. `frontend/app/players/[id]/page.tsx` — Ficha 360° do Jogador com visualização de dados cadastrais, tags com adição/remoção em tempo real, metadados flexíveis, tabela de consentimentos LGPD e linha do tempo cronológica.
29. `frontend/app/players/[id]/edit/page.tsx` — Formulário de edição de jogador pré-populado com os dados existentes.

---

## 3. Arquivos Alterados

1. `backend/app/Http/Controllers/Controller.php` — Adicionada trait `AuthorizesRequests` nativa do Laravel para suporte direto a `$this->authorize()`.
2. `backend/routes/api.php` — Registro dos endpoints de jogadores e tags agrupados sob middleware de autenticação Sanctum e contexto de tenant (`auth:sanctum`, `tenant.platform`).
3. `backend/database/seeders/DatabaseSeeder.php` — Registro do seeder `PlayerAndTagSeeder`.
4. `frontend/app/page.tsx` — Dashboard refatorado com o novo componente `AppLayout`, carregamento dinâmico de KPIs de jogadores direto da API e links diretos para o módulo de jogadores.
5. `README.md` — Documentação atualizada refletindo a conclusão da Fase 3.

---

## 4. Estrutura do Banco de Dados & Migrations

### Tabela `tags`
- `id` (bigserial primary key)
- `uuid` (uuid unique)
- `platform_id` (foreign key -> platforms.id onDelete cascade)
- `name` (varchar 100)
- `slug` (varchar 100)
- `color` (varchar 20, default `#10B981`)
- `description` (text nullable)
- `created_at`, `updated_at`
- **Restrições**: `UNIQUE(platform_id, slug)`
- **Índices**: `INDEX(platform_id)`

### Tabela `players`
- `id` (bigserial primary key)
- `uuid` (uuid unique)
- `platform_id` (foreign key -> platforms.id onDelete cascade)
- `external_id` (varchar 100 — identificador do sportsbook/cassino)
- `name` (varchar 150)
- `email` (varchar 150)
- `phone` (varchar 30 nullable)
- `cpf` (varchar 20 nullable)
- `birth_date` (date nullable)
- `gender` (varchar 20 default 'M')
- `state` (varchar 10 nullable)
- `city` (varchar 100 nullable)
- `affiliate` (varchar 100 nullable)
- `status` (varchar 30 default 'active' — active, inactive, churned, blocked, pending)
- `custom_fields` (jsonb default '{}')
- `last_login_at` (timestamp nullable)
- `created_at`, `updated_at`, `deleted_at` (SoftDeletes)
- **Restrições**: `UNIQUE(platform_id, external_id)`
- **Índices**: `INDEX(platform_id, status)`, `INDEX(platform_id, email)`, `INDEX(platform_id, phone)`, `INDEX(platform_id, state)`, `INDEX(platform_id, created_at)`

### Tabela `player_tags` (Pivot)
- `id` (bigserial primary key)
- `player_id` (foreign key -> players.id onDelete cascade)
- `tag_id` (foreign key -> tags.id onDelete cascade)
- `created_at`, `updated_at`
- **Restrições**: `UNIQUE(player_id, tag_id)`
- **Índices**: `INDEX(player_id)`, `INDEX(tag_id)`

### Tabela `consents` (LGPD)
- `id` (bigserial primary key)
- `player_id` (foreign key -> players.id onDelete cascade)
- `type` (varchar 50 — marketing, transactional, terms)
- `channel` (varchar 50 — email, sms, push, whatsapp)
- `status` (varchar 20 — granted, revoked)
- `ip_address` (varchar 45 nullable)
- `user_agent` (text nullable)
- `granted_at` (timestamp nullable)
- `revoked_at` (timestamp nullable)
- `created_at`, `updated_at`
- **Índices**: `INDEX(player_id, channel, status)`

---

## 5. Endpoints da API Criados

Todas as rotas exigem `Authorization: Bearer <token>` e o header de contexto de tenant `X-Platform-Id: <id>`.

| Método | Rota | Descrição | Permissão Exigida |
| :--- | :--- | :--- | :--- |
| `GET` | `/api/v1/players` | Listagem com filtros e paginação (offset ou cursor) | `players.view` |
| `POST` | `/api/v1/players` | Cadastro de novo jogador com deduplicação por plataforma | `players.create` |
| `GET` | `/api/v1/players/{id}` | Detalhes básicos do jogador | `players.view` |
| `PUT` | `/api/v1/players/{id}` | Atualização de dados cadastrais e custom fields | `players.update` |
| `DELETE` | `/api/v1/players/{id}` | Exclusão suave (Soft Delete) | `players.delete` |
| `GET` | `/api/v1/players/{id}/360` | Ficha 360° com histórico, consentimentos e timeline | `players.view` |
| `POST` | `/api/v1/players/{id}/tags` | Associação de tag ao jogador (valida escopo da tag) | `players.update` |
| `DELETE` | `/api/v1/players/{id}/tags/{tag_id}` | Desassociação de tag do jogador | `players.update` |
| `GET` | `/api/v1/tags` | Listagem de tags da plataforma ativa | `players.view` |
| `POST` | `/api/v1/tags` | Criação de nova tag na plataforma | `players.create` |
| `GET` | `/api/v1/tags/{id}` | Detalhes de uma tag | `players.view` |
| `PUT` | `/api/v1/tags/{id}` | Atualização de tag | `players.update` |
| `DELETE` | `/api/v1/tags/{id}` | Exclusão de tag | `players.delete` |

---

## 6. Deduplicação e Identificação Externa

1. **Chave Primária de Negócio**: `(platform_id, external_id)`.
2. **Isolamento de ID Externo**: O mesmo `external_id` (ex: `12345`) pode coexistir perfeitamente entre diferentes plataformas (`Bet Brasil` e `Bet Global`) sem colisão, sendo bloqueado apenas se duplicado dentro da **mesma** plataforma.
3. **Múltiplos Cadastros por E-mail**: O mesmo e-mail pode ser utilizado em plataformas distintas pelo apostador, garantindo que operadoras independentes não vazem nem compartilhem identidade.

---

## 7. Privacidade & Mascaramento LGPD

Para garantir conformidade com a LGPD:
- **E-mail**: `carlos.silva@exemplo.com.br` -> `ca***@exemplo.com.br`
- **Telefone**: `11987654321` -> `(11) 98***-**21`
- **CPF**: `12345678900` -> `123.***.***-00`
- O `PlayerResource` e o `Player360Resource` expõem os campos mascarados por padrão, prevenindo exposição acidental de dados pessoais em relatórios, telas de atendimento de operadores ou logs.

---

## 8. Paginação de Alta Performance (Offset & Cursor)

O `PlayerService` suporta duas modalidades de paginação:
1. **Offset Paginator (`pagination=offset`)**: Ideal para navegação tradicional por número de página na interface administrativa (`current_page`, `last_page`, `total`, `per_page`).
2. **Cursor Paginator (`pagination=cursor`)**: Projetado para consumo contínuo e sincronização de grandes volumes de dados (milhões de registros) sem degradação de performance comum ao `OFFSET/LIMIT` do SQL. Retorna `next_cursor` e `prev_cursor` indexados pelo ID sequencial.

---

## 9. Testes Automatizados & Validação

A suíte completa de testes foi executada localmente:

```bash
vendor/bin/phpunit
```

### Resultado da Execução:
```text
PHPUnit 11.5.56 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.3.31
Configuration: backend/phpunit.xml

.......................................................           55 / 55 (100%)

Time: 00:04.897, Memory: 54.00 MB

OK (55 tests, 264 assertions)
```

### Cenários Validados no `PlayerTest.php`:
1. `test_user_can_list_players_from_their_active_platform`
2. `test_user_cannot_see_players_from_another_platform`
3. `test_user_can_create_player_with_external_id_and_tags`
4. `test_cannot_create_duplicate_external_id_in_same_platform`
5. `test_can_create_same_external_id_in_different_platforms`
6. `test_user_can_view_player_360_view`
7. `test_user_cannot_view_player_from_different_platform`
8. `test_user_can_update_player`
9. `test_user_cannot_update_player_from_different_platform`
10. `test_user_can_delete_player_soft_delete`
11. `test_user_cannot_delete_player_from_different_platform`
12. `test_user_can_attach_and_detach_tag_to_player`
13. `test_cannot_attach_tag_from_another_platform_to_player`
14. `test_player_search_and_filters`
15. `test_player_cursor_pagination`
16. `test_lgpd_data_masking_in_resource`
17. `test_player_policy_denies_unauthorized_roles`
18. `test_tags_crud_endpoints`
19. `test_tags_cannot_cross_platforms`
20. `test_player_custom_fields_persistence`
21. `test_consents_association_and_360_display`
22. `test_timeline_structure_in_360_resource`
23. `test_validation_errors_on_create_player`
24. `test_validation_errors_on_update_player`
25. `test_super_admin_can_access_any_platform_player_when_platform_context_set`

---

## 10. Validação de Build do Frontend (Next.js 15)

O build de produção do Next.js foi compilado e validado:

```bash
npm run build
```

### Rotas Geradas:
```text
Route (app)                                 Size  First Load JS
┌ ○ /                                    4.37 kB         116 kB
├ ○ /_not-found                            996 B         104 kB
├ ○ /login                                4.6 kB         107 kB
├ ○ /players                             5.57 kB         117 kB
├ ƒ /players/[id]                         6.3 kB         118 kB
├ ƒ /players/[id]/edit                   4.42 kB         116 kB
├ ○ /players/new                         4.66 kB         116 kB
└ ○ /profile                             3.35 kB         111 kB
+ First Load JS shared by all             103 kB
```
**Resultado**: 0 erros de tipagem TypeScript e 0 warnings impeditivos.

---

## 11. Problemas Encontrados & Soluções Aplicadas

1. **Trait `AuthorizesRequests` ausente no Controller base do Laravel 12**:
   - *Problema*: Chamadas a `$this->authorize('viewAny', Player::class)` falhavam com `Call to undefined method`.
   - *Solução*: Trait `Illuminate\Foundation\Auth\Access\AuthorizesRequests` importada no `App\Http\Controllers\Controller`.
2. **Restrição de Global Scope em lookup de tags cross-platform**:
   - *Problema*: `Tag::findOrFail($tagId)` retornava 404 quando a tag era de outra plataforma, mascarando o erro como "não encontrado" em vez de indicar claramente a violação de segurança.
   - *Solução*: Uso de `Tag::withoutGlobalScopes()->find($tagId)` no controller para checar explicitamente `$tag->platform_id === $player->platform_id` e responder com status 422 e mensagem explicativa de restrição de plataforma.
3. **Incompatibilidade de tipagem no Next.js (Strict Null Checks)**:
   - *Problema*: O formulário de edição enviava `null` para campos opcionais vazios, gerando erro de TypeScript com `Partial<Player>`.
   - *Solução*: Tipagem do `Player` atualizada para aceitar explicitamente `string | null` em campos anuláveis.

---

## 12. Próximos Passos (Aguardando Aprovação para Fase 4)

A Fase 3 está concluída e integralmente testada. Nenhuma funcionalidade de fases posteriores foi antecipada.

Quando autorizado pelo operador, os passos para a **FASE 4** serão:
1. Módulo de Webhooks de Entrada (Ingestion API).
2. Endpoint `POST /api/v1/webhooks/{platform_slug}` com validação de assinatura HMAC-SHA256 (`webhook_secret`).
3. Normalização assíncrona de eventos de apostas (`player.created`, `player.updated`, `deposit.success`, `bet.placed`, `bet.settled`).
4. Enfileiramento via Redis/Horizon com retry policy e rastreabilidade idempotente.

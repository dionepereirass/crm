<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleAndPermissionSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Define Permissions by group
        $permissions = [
            // Users
            ['name' => 'Visualizar Usuários', 'slug' => 'users.view', 'group' => 'users'],
            ['name' => 'Criar Usuários', 'slug' => 'users.create', 'group' => 'users'],
            ['name' => 'Editar Usuários', 'slug' => 'users.update', 'group' => 'users'],
            ['name' => 'Excluir Usuários', 'slug' => 'users.delete', 'group' => 'users'],

            // Platforms
            ['name' => 'Visualizar Plataformas', 'slug' => 'platforms.view', 'group' => 'platforms'],
            ['name' => 'Criar Plataformas', 'slug' => 'platforms.create', 'group' => 'platforms'],
            ['name' => 'Editar Plataformas', 'slug' => 'platforms.update', 'group' => 'platforms'],
            ['name' => 'Excluir Plataformas', 'slug' => 'platforms.delete', 'group' => 'platforms'],

            // Players
            ['name' => 'Visualizar Jogadores', 'slug' => 'players.view', 'group' => 'players'],
            ['name' => 'Criar Jogadores', 'slug' => 'players.create', 'group' => 'players'],
            ['name' => 'Editar Jogadores', 'slug' => 'players.update', 'group' => 'players'],
            ['name' => 'Excluir Jogadores', 'slug' => 'players.delete', 'group' => 'players'],
            ['name' => 'Exportar Jogadores', 'slug' => 'players.export', 'group' => 'players'],

            // Campaigns (Fase 8)
            ['name' => 'Visualizar Campanhas', 'slug' => 'campaigns.view', 'group' => 'campaigns'],
            ['name' => 'Criar Campanhas', 'slug' => 'campaigns.create', 'group' => 'campaigns'],
            ['name' => 'Editar Campanhas', 'slug' => 'campaigns.update', 'group' => 'campaigns'],
            ['name' => 'Excluir Campanhas', 'slug' => 'campaigns.delete', 'group' => 'campaigns'],
            ['name' => 'Disparar Campanhas', 'slug' => 'campaigns.send', 'group' => 'campaigns'],
            ['name' => 'Validar Campanhas', 'slug' => 'campaigns.validate', 'group' => 'campaigns'],
            ['name' => 'Pré-visualizar Campanha', 'slug' => 'campaigns.preview', 'group' => 'campaigns'],
            ['name' => 'Enviar Teste de Campanha', 'slug' => 'campaigns.test', 'group' => 'campaigns'],
            ['name' => 'Lançar / Disparar Campanha', 'slug' => 'campaigns.launch', 'group' => 'campaigns'],
            ['name' => 'Pausar Campanha', 'slug' => 'campaigns.pause', 'group' => 'campaigns'],
            ['name' => 'Retomar Campanha', 'slug' => 'campaigns.resume', 'group' => 'campaigns'],
            ['name' => 'Cancelar Campanha', 'slug' => 'campaigns.cancel', 'group' => 'campaigns'],
            ['name' => 'Visualizar Destinatários da Campanha', 'slug' => 'campaigns.recipients', 'group' => 'campaigns'],
            ['name' => 'Visualizar Mensagens da Campanha', 'slug' => 'campaigns.messages', 'group' => 'campaigns'],
            ['name' => 'Visualizar Estatísticas da Campanha', 'slug' => 'campaigns.stats', 'group' => 'campaigns'],

            // Segments
            ['name' => 'Visualizar Segmentos', 'slug' => 'segments.view', 'group' => 'segments'],
            ['name' => 'Criar Segmentos', 'slug' => 'segments.create', 'group' => 'segments'],
            ['name' => 'Editar Segmentos', 'slug' => 'segments.update', 'group' => 'segments'],
            ['name' => 'Excluir Segmentos', 'slug' => 'segments.delete', 'group' => 'segments'],
            ['name' => 'Ativar/Desativar Segmentos', 'slug' => 'segments.activate', 'group' => 'segments'],
            ['name' => 'Pré-visualizar Audiência', 'slug' => 'segments.preview', 'group' => 'segments'],
            ['name' => 'Atualizar Cache de Segmento', 'slug' => 'segments.refresh', 'group' => 'segments'],
            ['name' => 'Listar Membros do Segmento', 'slug' => 'segments.members', 'group' => 'segments'],
            ['name' => 'Exportar Audiência', 'slug' => 'segments.export', 'group' => 'segments'],

            // Templates
            ['name' => 'Visualizar Templates', 'slug' => 'templates.view', 'group' => 'templates'],
            ['name' => 'Criar Templates', 'slug' => 'templates.create', 'group' => 'templates'],
            ['name' => 'Editar Templates', 'slug' => 'templates.update', 'group' => 'templates'],
            ['name' => 'Excluir Templates', 'slug' => 'templates.delete', 'group' => 'templates'],
            ['name' => 'Publicar Templates', 'slug' => 'templates.publish', 'group' => 'templates'],
            ['name' => 'Arquivar Templates', 'slug' => 'templates.archive', 'group' => 'templates'],
            ['name' => 'Pré-visualizar Templates', 'slug' => 'templates.preview', 'group' => 'templates'],
            ['name' => 'Duplicar Templates', 'slug' => 'templates.duplicate', 'group' => 'templates'],
            ['name' => 'Histórico de Versões', 'slug' => 'templates.versions', 'group' => 'templates'],
            ['name' => 'Restaurar Versões', 'slug' => 'templates.restore', 'group' => 'templates'],

            // Tags (Fase 3)
            ['name' => 'Visualizar Tags', 'slug' => 'tags.view', 'group' => 'tags'],
            ['name' => 'Criar Tags', 'slug' => 'tags.create', 'group' => 'tags'],
            ['name' => 'Editar Tags', 'slug' => 'tags.update', 'group' => 'tags'],
            ['name' => 'Excluir Tags', 'slug' => 'tags.delete', 'group' => 'tags'],
            ['name' => 'Atribuir Tags', 'slug' => 'tags.assign', 'group' => 'tags'],

            // Events (Fase 4)
            ['name' => 'Visualizar Eventos', 'slug' => 'events.view', 'group' => 'events'],
            ['name' => 'Reprocessar Eventos', 'slug' => 'events.reprocess', 'group' => 'events'],

            // Webhooks (Fase 4)
            ['name' => 'Visualizar Webhooks', 'slug' => 'webhooks.view', 'group' => 'webhooks'],
            ['name' => 'Gerenciar Webhooks', 'slug' => 'webhooks.manage', 'group' => 'webhooks'],

            // Providers (Fase 7)
            ['name' => 'Visualizar Provedores', 'slug' => 'providers.view', 'group' => 'providers'],
            ['name' => 'Criar Provedores', 'slug' => 'providers.create', 'group' => 'providers'],
            ['name' => 'Editar Provedores', 'slug' => 'providers.update', 'group' => 'providers'],
            ['name' => 'Excluir Provedores', 'slug' => 'providers.delete', 'group' => 'providers'],
            ['name' => 'Ativar/Desativar Provedores', 'slug' => 'providers.activate', 'group' => 'providers'],
            ['name' => 'Health Check Provedores', 'slug' => 'providers.health', 'group' => 'providers'],

            // Messages (Fase 7)
            ['name' => 'Visualizar Mensagens', 'slug' => 'messages.view', 'group' => 'messages'],
            ['name' => 'Enviar Teste de Mensagem', 'slug' => 'messages.test', 'group' => 'messages'],
            ['name' => 'Reprocessar Mensagem', 'slug' => 'messages.retry', 'group' => 'messages'],
            ['name' => 'Cancelar Mensagem', 'slug' => 'messages.cancel', 'group' => 'messages'],

            // Reports (Fase 12)
            ['name' => 'Visualizar Relatórios', 'slug' => 'reports.view', 'group' => 'reports'],
            ['name' => 'Criar Relatórios', 'slug' => 'reports.create', 'group' => 'reports'],
            ['name' => 'Exportar Relatórios', 'slug' => 'reports.export', 'group' => 'reports'],
            ['name' => 'Agendar Relatórios', 'slug' => 'reports.schedule', 'group' => 'reports'],
            ['name' => 'Excluir Relatórios', 'slug' => 'reports.delete', 'group' => 'reports'],

            // Tracking & Analytics (Fase 9 & 12)
            ['name' => 'Visualizar Analytics', 'slug' => 'analytics.view', 'group' => 'analytics'],
            ['name' => 'Analytics de Jogadores', 'slug' => 'analytics.players', 'group' => 'analytics'],
            ['name' => 'Analytics Financeiro', 'slug' => 'analytics.finance', 'group' => 'analytics'],
            ['name' => 'Analytics de Marketing', 'slug' => 'analytics.marketing', 'group' => 'analytics'],
            ['name' => 'Analytics de Apostas', 'slug' => 'analytics.betting', 'group' => 'analytics'],
            ['name' => 'Analytics de Automações', 'slug' => 'analytics.automations', 'group' => 'analytics'],
            ['name' => 'Analytics de Provedores', 'slug' => 'analytics.providers', 'group' => 'analytics'],
            ['name' => 'Analytics de Privacidade', 'slug' => 'analytics.privacy', 'group' => 'analytics'],
            ['name' => 'Exportar Analytics', 'slug' => 'analytics.export', 'group' => 'analytics'],
            ['name' => 'Visualizar Tracking', 'slug' => 'tracking.view', 'group' => 'tracking'],
            ['name' => 'Gerenciar Tracking', 'slug' => 'tracking.manage', 'group' => 'tracking'],

            // Operational Alerts (Fase 12)
            ['name' => 'Visualizar Alertas', 'slug' => 'alerts.view', 'group' => 'alerts'],
            ['name' => 'Criar Regras de Alerta', 'slug' => 'alerts.create', 'group' => 'alerts'],
            ['name' => 'Editar Regras de Alerta', 'slug' => 'alerts.update', 'group' => 'alerts'],
            ['name' => 'Excluir Regras de Alerta', 'slug' => 'alerts.delete', 'group' => 'alerts'],
            ['name' => 'Resolver Alertas', 'slug' => 'alerts.resolve', 'group' => 'alerts'],

            // Automations (Fase 10)
            ['name' => 'Visualizar Automações', 'slug' => 'automations.view', 'group' => 'automations'],
            ['name' => 'Criar Automações', 'slug' => 'automations.create', 'group' => 'automations'],
            ['name' => 'Editar Automações', 'slug' => 'automations.update', 'group' => 'automations'],
            ['name' => 'Excluir Automações', 'slug' => 'automations.delete', 'group' => 'automations'],
            ['name' => 'Ativar Automações', 'slug' => 'automations.activate', 'group' => 'automations'],
            ['name' => 'Pausar Automações', 'slug' => 'automations.pause', 'group' => 'automations'],
            ['name' => 'Visualizar Execuções de Automações', 'slug' => 'automations.runs', 'group' => 'automations'],
            ['name' => 'Cancelar Execução de Automação', 'slug' => 'automations.cancel', 'group' => 'automations'],

            // Privacy & LGPD (Fase 11)
            ['name' => 'Visualizar Privacidade & LGPD', 'slug' => 'privacy.view', 'group' => 'privacy'],
            ['name' => 'Visualizar Solicitações de Titular', 'slug' => 'privacy.requests.view', 'group' => 'privacy'],
            ['name' => 'Criar Solicitações de Titular', 'slug' => 'privacy.requests.create', 'group' => 'privacy'],
            ['name' => 'Editar Solicitações de Titular', 'slug' => 'privacy.requests.update', 'group' => 'privacy'],
            ['name' => 'Processar Solicitações de Titular', 'slug' => 'privacy.requests.process', 'group' => 'privacy'],
            ['name' => 'Exportar Dados de Titular', 'slug' => 'privacy.requests.export', 'group' => 'privacy'],
            ['name' => 'Executar Exclusão / Anonimização de Titular', 'slug' => 'privacy.requests.delete', 'group' => 'privacy'],
            ['name' => 'Visualizar Consentimentos', 'slug' => 'privacy.consent.view', 'group' => 'privacy'],
            ['name' => 'Gerenciar Consentimentos', 'slug' => 'privacy.consent.update', 'group' => 'privacy'],
            ['name' => 'Visualizar Auditoria de Privacidade', 'slug' => 'privacy.audit.view', 'group' => 'privacy'],
            ['name' => 'Visualizar Políticas de Retenção', 'slug' => 'privacy.retention.view', 'group' => 'privacy'],
            ['name' => 'Gerenciar Políticas de Retenção', 'slug' => 'privacy.retention.manage', 'group' => 'privacy'],
            ['name' => 'Visualizar Acessos a Dados Pessoais', 'slug' => 'privacy.data_access.view', 'group' => 'privacy'],

            // Settings
            ['name' => 'Visualizar Configurações', 'slug' => 'settings.view', 'group' => 'settings'],
            ['name' => 'Editar Configurações', 'slug' => 'settings.update', 'group' => 'settings'],
        ];

        $permissionModels = [];
        foreach ($permissions as $perm) {
            $permissionModels[$perm['slug']] = Permission::updateOrCreate(
                ['slug' => $perm['slug']],
                $perm
            );
        }

        // 2. Define Roles
        $roles = [
            'SUPER_ADMIN' => [
                'name' => 'Super Administrador',
                'description' => 'Acesso irrestrito a todas as plataformas, configurações e auditoria.',
            ],
            'ADMIN' => [
                'name' => 'Administrador de Plataforma',
                'description' => 'Gestão operacional e administrativa completa da plataforma designada.',
            ],
            'MARKETING' => [
                'name' => 'Especialista de Marketing',
                'description' => 'Criação e envio de campanhas, gestão de templates, segmentos e relatórios.',
            ],
            'SUPPORT' => [
                'name' => 'Agente de Suporte',
                'description' => 'Visualização de ficha de jogadores, aplicação de tags e atendimento operacional.',
            ],
            'ANALYST' => [
                'name' => 'Analista de Dados & BI',
                'description' => 'Visualização analítica de jogadores, taxas de conversão e exportação de relatórios.',
            ],
        ];

        $roleModels = [];
        foreach ($roles as $slug => $data) {
            $roleModels[$slug] = Role::updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => $data['name'],
                    'slug' => $slug,
                    'description' => $data['description'],
                ]
            );
        }

        // 3. Map Permissions to Roles
        // SUPER_ADMIN receives all permissions
        $roleModels['SUPER_ADMIN']->permissions()->sync(collect($permissionModels)->pluck('id'));

        // ADMIN receives all except platform creation/deletion
        $adminPerms = collect($permissionModels)->filter(function ($p) {
            return !in_array($p->slug, ['platforms.create', 'platforms.delete']);
        })->pluck('id');
        $roleModels['ADMIN']->permissions()->sync($adminPerms);

        // MARKETING
        $marketingPermSlugs = [
            'players.view',
            'campaigns.view', 'campaigns.create', 'campaigns.update', 'campaigns.delete', 'campaigns.send',
            'campaigns.validate', 'campaigns.preview', 'campaigns.test', 'campaigns.launch',
            'campaigns.pause', 'campaigns.resume', 'campaigns.cancel', 'campaigns.recipients', 'campaigns.messages', 'campaigns.stats',
            'segments.view', 'segments.create', 'segments.update', 'segments.activate', 'segments.preview', 'segments.refresh', 'segments.members', 'segments.export',
            'templates.view', 'templates.create', 'templates.update', 'templates.publish', 'templates.archive', 'templates.preview', 'templates.duplicate', 'templates.versions', 'templates.restore',
            'providers.view',
            'messages.view', 'messages.test', 'messages.retry',
            'automations.view', 'automations.create', 'automations.update', 'automations.delete',
            'automations.activate', 'automations.pause', 'automations.runs', 'automations.cancel',
            'reports.view', 'reports.create', 'reports.export', 'reports.schedule',
            'events.view',
            'analytics.view', 'analytics.players', 'analytics.marketing', 'analytics.automations', 'analytics.providers', 'analytics.export', 'tracking.view',
            'alerts.view',
            'privacy.view', 'privacy.consent.view', 'privacy.consent.update',
        ];
        $roleModels['MARKETING']->permissions()->sync(
            collect($permissionModels)->whereIn('slug', $marketingPermSlugs)->pluck('id')
        );

        // SUPPORT
        $supportPermSlugs = [
            'players.view', 'players.update',
            'templates.view',
            'messages.view', 'messages.retry',
            'automations.view',
            'reports.view',
            'events.view',
            'alerts.view',
            'privacy.view', 'privacy.requests.view', 'privacy.requests.create', 'privacy.consent.view',
        ];
        $roleModels['SUPPORT']->permissions()->sync(
            collect($permissionModels)->whereIn('slug', $supportPermSlugs)->pluck('id')
        );

        // ANALYST
        $analystPermSlugs = [
            'players.view', 'players.export',
            'campaigns.view', 'campaigns.preview', 'campaigns.recipients', 'campaigns.messages', 'campaigns.stats',
            'segments.view', 'segments.preview', 'segments.members', 'segments.export',
            'templates.view', 'templates.preview', 'templates.versions',
            'providers.view',
            'messages.view',
            'automations.view', 'automations.runs',
            'reports.view', 'reports.create', 'reports.export', 'reports.schedule',
            'events.view',
            'analytics.view', 'analytics.players', 'analytics.finance', 'analytics.marketing', 'analytics.betting', 'analytics.automations', 'analytics.providers', 'analytics.privacy', 'analytics.export', 'tracking.view',
            'alerts.view',
            'privacy.view', 'privacy.consent.view', 'privacy.audit.view',
        ];
        $roleModels['ANALYST']->permissions()->sync(
            collect($permissionModels)->whereIn('slug', $analystPermSlugs)->pluck('id')
        );
    }
}

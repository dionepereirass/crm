<?php

use App\Http\Controllers\Api\V1\AlertController;
use App\Http\Controllers\Api\V1\AnalyticsController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\AutomationController;
use App\Http\Controllers\Api\V1\CampaignController;
use App\Http\Controllers\Api\V1\EventController;
use App\Http\Controllers\Api\V1\HealthCheckController;
use App\Http\Controllers\Api\V1\MessageController;
use App\Http\Controllers\Api\V1\PlayerController;
use App\Http\Controllers\Api\V1\PrivacyController;
use App\Http\Controllers\Api\V1\ProviderController;
use App\Http\Controllers\Api\V1\ProviderWebhookController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Controllers\Api\V1\SegmentController;
use App\Http\Controllers\Api\V1\TagController;
use App\Http\Controllers\Api\V1\TemplateController;
use App\Http\Controllers\Api\V1\TrackingController;
use App\Http\Controllers\Api\V1\WebhookController;
use App\Http\Controllers\Api\V1\WebhookLogController;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\TenantPlatformContext;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — BET CRM (v1)
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->group(function () {
    // Health Checks
    Route::get('/health', [HealthCheckController::class, 'index'])->name('api.v1.health');
    Route::get('/health/database', [HealthCheckController::class, 'database'])->name('api.v1.health.database');
    Route::get('/health/redis', [HealthCheckController::class, 'redis'])->name('api.v1.health.redis');
    Route::get('/health/queue', [HealthCheckController::class, 'queue'])->name('api.v1.health.queue');
    Route::get('/health/providers', [HealthCheckController::class, 'providers'])->name('api.v1.health.providers');

    // Public Route: Login
    Route::post('/auth/login', [AuthController::class, 'login'])->name('api.v1.auth.login');

    // Public Ingestion Endpoint: Webhooks (HMAC-SHA256 authenticated + Rate limited per platform)
    Route::post('/webhooks/{platform_slug}', [WebhookController::class, 'handle'])
        ->middleware('throttle:webhooks')
        ->name('api.v1.webhooks.handle');

    // Protected Routes (Sanctum + Tenant Platform Context + Active User)
    Route::middleware(['auth:sanctum', EnsureUserIsActive::class, TenantPlatformContext::class])->group(function () {
        // Auth Session
        Route::post('/auth/logout', [AuthController::class, 'logout'])->name('api.v1.auth.logout');
        Route::get('/auth/me', [AuthController::class, 'me'])->name('api.v1.auth.me');
        Route::post('/auth/revoke', [AuthController::class, 'revoke'])->name('api.v1.auth.revoke');

        // Players CRUD & 360° Profile
        Route::get('/players/{player}/360', [PlayerController::class, 'show360'])->name('api.v1.players.360');
        Route::post('/players/{player}/tags', [PlayerController::class, 'attachTag'])->name('api.v1.players.tags.attach');
        Route::delete('/players/{player}/tags/{tag}', [PlayerController::class, 'detachTag'])->name('api.v1.players.tags.detach');
        Route::apiResource('players', PlayerController::class);

        // Tags CRUD
        Route::apiResource('tags', TagController::class);

        // Segments Engine & Rules
        Route::get('/segment-fields', [SegmentController::class, 'fields'])->name('api.v1.segments.fields');
        Route::get('/segment-operators', [SegmentController::class, 'operators'])->name('api.v1.segments.operators');
        Route::post('/segments/preview', [SegmentController::class, 'preview'])->name('api.v1.segments.preview');
        Route::get('/segments/{segment}/preview', [SegmentController::class, 'previewExisting'])->name('api.v1.segments.preview.existing');
        Route::post('/segments/{segment}/activate', [SegmentController::class, 'activate'])->name('api.v1.segments.activate');
        Route::post('/segments/{segment}/deactivate', [SegmentController::class, 'deactivate'])->name('api.v1.segments.deactivate');
        Route::post('/segments/{segment}/refresh', [SegmentController::class, 'refresh'])->name('api.v1.segments.refresh');
        Route::get('/segments/{segment}/members', [SegmentController::class, 'members'])->name('api.v1.segments.members');
        Route::post('/segments/{segment}/duplicate', [SegmentController::class, 'duplicate'])->name('api.v1.segments.duplicate');
        Route::apiResource('segments', SegmentController::class);

        // Templates System & Versions
        Route::get('/template-variables', [TemplateController::class, 'variables'])->name('api.v1.templates.variables');
        Route::get('/template-categories', [TemplateController::class, 'categories'])->name('api.v1.templates.categories');
        Route::post('/templates/{template}/duplicate', [TemplateController::class, 'duplicate'])->name('api.v1.templates.duplicate');
        Route::post('/templates/{template}/activate', [TemplateController::class, 'activate'])->name('api.v1.templates.activate');
        Route::post('/templates/{template}/archive', [TemplateController::class, 'archive'])->name('api.v1.templates.archive');
        Route::post('/templates/{template}/preview', [TemplateController::class, 'preview'])->name('api.v1.templates.preview');
        Route::get('/templates/{template}/versions', [TemplateController::class, 'versions'])->name('api.v1.templates.versions.index');
        Route::post('/templates/{template}/versions', [TemplateController::class, 'storeVersion'])->name('api.v1.templates.versions.store');
        Route::get('/templates/{template}/versions/{version}', [TemplateController::class, 'showVersion'])->name('api.v1.templates.versions.show');
        Route::post('/templates/{template}/versions/{version}/publish', [TemplateController::class, 'publishVersion'])->name('api.v1.templates.versions.publish');
        Route::post('/templates/{template}/versions/{version}/restore', [TemplateController::class, 'restoreVersion'])->name('api.v1.templates.versions.restore');
        Route::apiResource('templates', TemplateController::class);

        // Events & Reprocessing
        Route::get('/events', [EventController::class, 'index'])->name('api.v1.events.index');
        Route::get('/events/{event}', [EventController::class, 'show'])->name('api.v1.events.show');
        Route::post('/events/{event}/reprocess', [EventController::class, 'reprocess'])->name('api.v1.events.reprocess');

        // Webhook Ingestion Logs
        Route::get('/webhook-logs', [WebhookLogController::class, 'index'])->name('api.v1.webhook-logs.index');
        Route::get('/webhook-logs/{webhook_log}', [WebhookLogController::class, 'show'])->name('api.v1.webhook-logs.show');

        // Providers (Fase 7)
        Route::post('/providers/{provider}/activate', [ProviderController::class, 'activate'])->name('api.v1.providers.activate');
        Route::post('/providers/{provider}/deactivate', [ProviderController::class, 'deactivate'])->name('api.v1.providers.deactivate');
        Route::post('/providers/{provider}/health-check', [ProviderController::class, 'healthCheck'])->name('api.v1.providers.health-check');
        Route::apiResource('providers', ProviderController::class);

        // Messages & Envio (Fase 7)
        Route::get('/messages', [MessageController::class, 'index'])->name('api.v1.messages.index');
        Route::post('/messages/test', [MessageController::class, 'test'])
            ->middleware('throttle:messages_test')
            ->name('api.v1.messages.test');
        Route::get('/messages/{message}', [MessageController::class, 'show'])->name('api.v1.messages.show');
        Route::get('/messages/{message}/events', [MessageController::class, 'events'])->name('api.v1.messages.events');
        Route::post('/messages/{message}/retry', [MessageController::class, 'retry'])->name('api.v1.messages.retry');
        Route::post('/messages/{message}/cancel', [MessageController::class, 'cancel'])->name('api.v1.messages.cancel');

        // Campaigns System (Fase 8 & 9)
        Route::get('/campaigns/{id}/analytics', [AnalyticsController::class, 'campaignAnalytics'])->name('api.v1.campaigns.analytics');
        Route::get('/campaigns/{id}/events', [AnalyticsController::class, 'campaignEvents'])->name('api.v1.campaigns.events');
        Route::post('/campaigns/{id}/rebuild-analytics', [AnalyticsController::class, 'campaignRebuild'])->name('api.v1.campaigns.rebuild-analytics');
        Route::post('/campaigns/{id}/validate', [CampaignController::class, 'validateCampaign'])->name('api.v1.campaigns.validate');
        Route::post('/campaigns/{id}/preview', [CampaignController::class, 'preview'])->name('api.v1.campaigns.preview');
        Route::post('/campaigns/{id}/test', [CampaignController::class, 'test'])->name('api.v1.campaigns.test');
        Route::post('/campaigns/{id}/launch', [CampaignController::class, 'launch'])->name('api.v1.campaigns.launch');
        Route::post('/campaigns/{id}/pause', [CampaignController::class, 'pause'])->name('api.v1.campaigns.pause');
        Route::post('/campaigns/{id}/resume', [CampaignController::class, 'resume'])->name('api.v1.campaigns.resume');
        Route::post('/campaigns/{id}/cancel', [CampaignController::class, 'cancel'])->name('api.v1.campaigns.cancel');
        Route::get('/campaigns/{id}/recipients', [CampaignController::class, 'recipients'])->name('api.v1.campaigns.recipients');
        Route::get('/campaigns/{id}/messages', [CampaignController::class, 'messages'])->name('api.v1.campaigns.messages');
        Route::get('/campaigns/{id}/stats', [CampaignController::class, 'stats'])->name('api.v1.campaigns.stats');
        Route::apiResource('campaigns', CampaignController::class, ['parameters' => ['campaigns' => 'id']]);

        // Analytics Geral (Fase 9)
        Route::get('/analytics/overview', [AnalyticsController::class, 'overview'])->name('api.v1.analytics.overview');
        Route::get('/analytics/campaigns/export', [AnalyticsController::class, 'export'])
            ->middleware('throttle:exports')
            ->name('api.v1.analytics.campaigns.export');
        Route::get('/analytics/campaigns', [AnalyticsController::class, 'campaigns'])->name('api.v1.analytics.campaigns');

        // Automações e Jornadas (Fase 10)
        Route::post('/automations/{id}/activate', [AutomationController::class, 'activate'])->name('api.v1.automations.activate');
        Route::post('/automations/{id}/pause', [AutomationController::class, 'pause'])->name('api.v1.automations.pause');
        Route::post('/automations/{id}/deactivate', [AutomationController::class, 'deactivate'])->name('api.v1.automations.deactivate');
        Route::get('/automations/{id}/graph', [AutomationController::class, 'getGraph'])->name('api.v1.automations.get-graph');
        Route::put('/automations/{id}/graph', [AutomationController::class, 'saveGraph'])->name('api.v1.automations.save-graph');
        Route::post('/automations/{id}/validate', [AutomationController::class, 'validateGraph'])->name('api.v1.automations.validate');
        Route::post('/automations/{id}/preview', [AutomationController::class, 'preview'])->name('api.v1.automations.preview');
        Route::get('/automations/{id}/metrics', [AutomationController::class, 'metrics'])->name('api.v1.automations.metrics');
        Route::get('/automations/{id}/runs', [AutomationController::class, 'runs'])->name('api.v1.automations.runs');
        Route::get('/automations/{id}/runs/{runId}', [AutomationController::class, 'showRun'])->name('api.v1.automations.show-run');
        Route::post('/automations/{id}/runs/{runId}/cancel', [AutomationController::class, 'cancelRun'])->name('api.v1.automations.cancel-run');
        Route::apiResource('automations', AutomationController::class, ['parameters' => ['automations' => 'id']]);

        // Privacidade, Consentimento & LGPD (Fase 11)
        Route::get('/privacy/dashboard', [PrivacyController::class, 'dashboard'])->name('api.v1.privacy.dashboard');
        Route::get('/privacy/consents', [PrivacyController::class, 'consents'])->name('api.v1.privacy.consents');
        Route::post('/privacy/consents/grant', [PrivacyController::class, 'grantConsent'])->name('api.v1.privacy.consents.grant');
        Route::post('/privacy/consents/revoke', [PrivacyController::class, 'revokeConsent'])->name('api.v1.privacy.consents.revoke');
        Route::get('/privacy/consents/{id}/history', [PrivacyController::class, 'consentHistory'])->name('api.v1.privacy.consents.history');

        Route::get('/privacy/requests', [PrivacyController::class, 'requests'])->name('api.v1.privacy.requests');
        Route::post('/privacy/requests', [PrivacyController::class, 'createRequest'])->name('api.v1.privacy.requests.create');
        Route::get('/privacy/requests/{id}', [PrivacyController::class, 'getRequest'])->name('api.v1.privacy.requests.show');
        Route::post('/privacy/requests/{id}/assign', [PrivacyController::class, 'assignRequest'])->name('api.v1.privacy.requests.assign');
        Route::post('/privacy/requests/{id}/process', [PrivacyController::class, 'processRequest'])->name('api.v1.privacy.requests.process');
        Route::post('/privacy/requests/{id}/complete', [PrivacyController::class, 'completeRequest'])->name('api.v1.privacy.requests.complete');
        Route::post('/privacy/requests/{id}/reject', [PrivacyController::class, 'rejectRequest'])->name('api.v1.privacy.requests.reject');
        Route::post('/privacy/requests/{id}/cancel', [PrivacyController::class, 'cancelRequest'])->name('api.v1.privacy.requests.cancel');
        Route::post('/privacy/requests/{id}/export', [PrivacyController::class, 'exportRequest'])
            ->middleware('throttle:exports')
            ->name('api.v1.privacy.requests.export');

        Route::get('/privacy/retention', [PrivacyController::class, 'retentionPolicies'])->name('api.v1.privacy.retention');
        Route::post('/privacy/retention', [PrivacyController::class, 'saveRetentionPolicy'])->name('api.v1.privacy.retention.save');
        Route::post('/privacy/retention/process', [PrivacyController::class, 'processRetention'])->name('api.v1.privacy.retention.process');

        Route::get('/privacy/audit', [PrivacyController::class, 'auditLogs'])->name('api.v1.privacy.audit');
        Route::get('/privacy/players/{playerId}/export', [PrivacyController::class, 'exportPlayer'])
            ->middleware('throttle:exports')
            ->name('api.v1.privacy.players.export');
        Route::post('/privacy/players/{playerId}/anonymize', [PrivacyController::class, 'anonymizePlayer'])->name('api.v1.privacy.players.anonymize');

        // Dashboard & Analytics Profundo (Fase 12)
        Route::get('/analytics/dashboard', [AnalyticsController::class, 'dashboard'])->name('api.v1.analytics.dashboard');
        Route::get('/analytics/players', [AnalyticsController::class, 'players'])->name('api.v1.analytics.players');
        Route::get('/analytics/retention', [AnalyticsController::class, 'retention'])->name('api.v1.analytics.retention');
        Route::get('/analytics/churn', [AnalyticsController::class, 'churn'])->name('api.v1.analytics.churn');
        Route::get('/analytics/finance', [AnalyticsController::class, 'finance'])->name('api.v1.analytics.finance');
        Route::get('/analytics/betting', [AnalyticsController::class, 'betting'])->name('api.v1.analytics.betting');
        Route::get('/analytics/marketing', [AnalyticsController::class, 'marketing'])->name('api.v1.analytics.marketing');
        Route::get('/analytics/templates', [AnalyticsController::class, 'templates'])->name('api.v1.analytics.templates');
        Route::get('/analytics/providers', [AnalyticsController::class, 'providers'])->name('api.v1.analytics.providers');
        Route::get('/analytics/automations', [AnalyticsController::class, 'automations'])->name('api.v1.analytics.automations');
        Route::get('/analytics/privacy', [AnalyticsController::class, 'privacy'])->name('api.v1.analytics.privacy');
        Route::get('/analytics/segments', [AnalyticsController::class, 'segments'])->name('api.v1.analytics.segments');
        Route::post('/analytics/cache/invalidate', [AnalyticsController::class, 'invalidateCache'])->name('api.v1.analytics.cache.invalidate');

        // Relatórios & Exportação (Fase 12)
        Route::get('/reports', [ReportController::class, 'index'])->name('api.v1.reports.index');
        Route::post('/reports/export', [ReportController::class, 'export'])
            ->middleware('throttle:exports')
            ->name('api.v1.reports.export');
        Route::get('/reports/scheduled', [ReportController::class, 'listScheduled'])->name('api.v1.reports.scheduled.list');
        Route::post('/reports/scheduled', [ReportController::class, 'storeScheduled'])->name('api.v1.reports.scheduled.store');
        Route::delete('/reports/scheduled/{id}', [ReportController::class, 'destroyScheduled'])->name('api.v1.reports.scheduled.destroy');

        // Alertas Operacionais (Fase 12)
        Route::get('/alerts', [AlertController::class, 'index'])->name('api.v1.alerts.index');
        Route::post('/alerts/{id}/acknowledge', [AlertController::class, 'acknowledge'])->name('api.v1.alerts.acknowledge');
        Route::post('/alerts/{id}/resolve', [AlertController::class, 'resolve'])->name('api.v1.alerts.resolve');
        Route::get('/alerts/rules', [AlertController::class, 'listRules'])->name('api.v1.alerts.rules.list');
        Route::post('/alerts/rules', [AlertController::class, 'storeRule'])->name('api.v1.alerts.rules.store');
        Route::put('/alerts/rules/{id}', [AlertController::class, 'updateRule'])->name('api.v1.alerts.rules.update');
        Route::delete('/alerts/rules/{id}', [AlertController::class, 'destroyRule'])->name('api.v1.alerts.rules.destroy');
        Route::post('/alerts/evaluate', [AlertController::class, 'evaluate'])->name('api.v1.alerts.evaluate');
    });

    // Tracking Endpoints (Públicos com Rate Limiting - Fase 9)
    Route::middleware('throttle:120,1')->group(function () {
        Route::get('/tracking/open/{token}', [TrackingController::class, 'open'])->name('api.v1.tracking.open');
        Route::get('/tracking/click/{token}', [TrackingController::class, 'click'])->name('api.v1.tracking.click');
        Route::get('/tracking/unsubscribe/{token}', [TrackingController::class, 'unsubscribe'])->name('api.v1.tracking.unsubscribe');
    });

    // Provedores Webhooks Callbacks (Públicos com Validação por Driver)
    Route::post('/providers/webhooks/{driver}', [ProviderWebhookController::class, 'handle'])->name('api.v1.providers.webhooks');
});

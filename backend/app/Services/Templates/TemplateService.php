<?php

namespace App\Services\Templates;

use App\Models\Template;
use App\Models\TemplateVersion;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;

class TemplateService
{
    public function __construct(
        protected TemplateSanitizer $sanitizer,
        protected TemplateRenderer $renderer
    ) {}

    /**
     * Lista os templates da plataforma com filtros e paginação.
     */
    public function list(int $platformId, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Template::withoutGlobalScopes()
            ->where('platform_id', $platformId)
            ->with(['currentVersion', 'creator:id,name,email', 'updater:id,name,email'])
            ->withCount('versions');

        if (!empty($filters['channel'])) {
            $query->where('channel', strtoupper($filters['channel']));
        }

        if (!empty($filters['status'])) {
            $query->where('status', strtoupper($filters['status']));
        }

        if (!empty($filters['category'])) {
            $query->where('category', strtoupper($filters['category']));
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('description', 'LIKE', "%{$search}%")
                  ->orWhere('slug', 'LIKE', "%{$search}%");
            });
        }

        return $query->orderBy('updated_at', 'desc')->paginate($perPage);
    }

    /**
     * Busca um template por ID ou UUID com isolamento por plataforma.
     */
    public function getById(int|string $id, int $platformId): Template
    {
        $query = Template::withoutGlobalScopes()
            ->where('platform_id', $platformId)
            ->with(['currentVersion', 'creator:id,name,email', 'updater:id,name,email'])
            ->withCount('versions');

        if (is_numeric($id)) {
            $template = $query->where('id', (int) $id)->first();
        } else {
            $template = $query->where('uuid', $id)->first();
        }

        if (!$template) {
            throw new InvalidArgumentException("Template não encontrado para a plataforma atual.");
        }

        return $template;
    }

    /**
     * Cria um novo template com sua primeira versão (v1).
     */
    public function create(int $platformId, array $data, ?int $userId = null): Template
    {
        $channel = strtoupper($data['channel'] ?? 'EMAIL');
        $this->validateChannelVariables($channel, $data);

        return DB::transaction(function () use ($platformId, $data, $channel, $userId) {
            $status = strtoupper($data['status'] ?? 'DRAFT');

            // 1. Cria o registro mestre do Template
            $template = Template::create([
                'uuid' => (string) Str::uuid(),
                'platform_id' => $platformId,
                'name' => $data['name'],
                'slug' => Str::slug($data['name']) . '-' . Str::random(6),
                'description' => $data['description'] ?? null,
                'channel' => $channel,
                'status' => $status,
                'category' => strtoupper($data['category'] ?? 'GENERAL'),
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);

            // 2. Prepara e sanitiza conteúdo da Versão 1
            $versionData = $this->prepareVersionContent($channel, $data, $userId, 1);
            $versionData['status'] = $status === 'ACTIVE' ? 'PUBLISHED' : 'DRAFT';
            $versionData['template_id'] = $template->id;

            $version = TemplateVersion::create($versionData);

            // 3. Atualiza ponteiro da versão atual
            $template->update(['current_version_id' => $version->id]);

            Log::info("Template criado com sucesso.", [
                'action' => 'template.created',
                'template_id' => $template->id,
                'platform_id' => $platformId,
                'version' => 1,
                'user_id' => $userId,
            ]);

            return $template->fresh(['currentVersion', 'creator', 'updater']);
        });
    }

    /**
     * Atualiza um template. Se houver alterações no conteúdo:
     * - Se a versão atual já estiver PUBLISHED: cria uma nova versão N+1 em DRAFT (imutabilidade).
     * - Se a versão atual ainda for DRAFT: atualiza o rascunho diretamente.
     */
    public function update(Template $template, array $data, ?int $userId = null): Template
    {
        $channel = $template->channel;
        $this->validateChannelVariables($channel, $data);

        return DB::transaction(function () use ($template, $data, $channel, $userId) {
            $updateMeta = [];
            if (isset($data['name'])) $updateMeta['name'] = $data['name'];
            if (array_key_exists('description', $data)) $updateMeta['description'] = $data['description'];
            if (isset($data['category'])) $updateMeta['category'] = strtoupper($data['category']);
            if (isset($data['status'])) $updateMeta['status'] = strtoupper($data['status']);
            if ($userId) $updateMeta['updated_by'] = $userId;

            $hasContentChanges = array_key_exists('subject', $data) ||
                array_key_exists('preheader', $data) ||
                array_key_exists('html_content', $data) ||
                array_key_exists('text_content', $data) ||
                array_key_exists('sms_content', $data);

            if ($hasContentChanges) {
                $currentVersion = $template->currentVersion;

                if ($currentVersion && $currentVersion->isPublished()) {
                    // Versão atual é publicada e imutável -> cria nova versão N+1 em DRAFT
                    $maxVersion = TemplateVersion::where('template_id', $template->id)->max('version') ?? 1;
                    $newVersionNum = $maxVersion + 1;

                    $versionPayload = array_merge([
                        'subject' => $currentVersion->subject,
                        'preheader' => $currentVersion->preheader,
                        'html_content' => $currentVersion->html_content,
                        'text_content' => $currentVersion->text_content,
                        'sms_content' => $currentVersion->sms_content,
                    ], $data);

                    $versionData = $this->prepareVersionContent($channel, $versionPayload, $userId, $newVersionNum);
                    $versionData['template_id'] = $template->id;
                    $versionData['status'] = 'DRAFT';

                    TemplateVersion::create($versionData);

                    Log::info("Nova versão de template gerada a partir de edição.", [
                        'action' => 'template.version.created',
                        'template_id' => $template->id,
                        'new_version' => $newVersionNum,
                        'user_id' => $userId,
                    ]);
                } elseif ($currentVersion && $currentVersion->isDraft()) {
                    // Versão atual é rascunho -> atualiza in-place
                    $versionData = $this->prepareVersionContent($channel, array_merge([
                        'subject' => $currentVersion->subject,
                        'preheader' => $currentVersion->preheader,
                        'html_content' => $currentVersion->html_content,
                        'text_content' => $currentVersion->text_content,
                        'sms_content' => $currentVersion->sms_content,
                    ], $data), $userId, $currentVersion->version);

                    $currentVersion->update($versionData);
                }
            }

            if (!empty($updateMeta)) {
                $template->update($updateMeta);
            }

            Log::info("Template atualizado.", [
                'action' => 'template.updated',
                'template_id' => $template->id,
                'user_id' => $userId,
            ]);

            return $template->fresh(['currentVersion', 'creator', 'updater']);
        });
    }

    /**
     * Exclui (soft delete) um template.
     */
    public function delete(Template $template, ?int $userId = null): bool
    {
        Log::info("Template excluído.", [
            'action' => 'template.deleted',
            'template_id' => $template->id,
            'user_id' => $userId,
        ]);

        return (bool) $template->delete();
    }

    /**
     * Ativa um template.
     */
    public function activate(Template $template, ?int $userId = null): Template
    {
        $template->update([
            'status' => 'ACTIVE',
            'updated_by' => $userId,
        ]);

        Log::info("Template ativado.", [
            'action' => 'template.published',
            'template_id' => $template->id,
            'user_id' => $userId,
        ]);

        return $template->fresh(['currentVersion', 'creator', 'updater']);
    }

    /**
     * Arquiva um template.
     */
    public function archive(Template $template, ?int $userId = null): Template
    {
        $template->update([
            'status' => 'ARCHIVED',
            'updated_by' => $userId,
        ]);

        Log::info("Template arquivado.", [
            'action' => 'template.archived',
            'template_id' => $template->id,
            'user_id' => $userId,
        ]);

        return $template->fresh(['currentVersion', 'creator', 'updater']);
    }

    /**
     * Clona/duplica um template, gerando um novo template com status DRAFT e versão 1.
     */
    public function duplicate(Template $template, ?int $userId = null): Template
    {
        return DB::transaction(function () use ($template, $userId) {
            $clone = Template::create([
                'uuid' => (string) Str::uuid(),
                'platform_id' => $template->platform_id,
                'name' => $template->name . ' (Cópia)',
                'slug' => Str::slug($template->name . '-copia') . '-' . Str::random(6),
                'description' => $template->description,
                'channel' => $template->channel,
                'status' => 'DRAFT',
                'category' => $template->category,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);

            $current = $template->currentVersion;

            $version = TemplateVersion::create([
                'uuid' => (string) Str::uuid(),
                'template_id' => $clone->id,
                'version' => 1,
                'status' => 'DRAFT',
                'subject' => $current?->subject,
                'preheader' => $current?->preheader,
                'html_content' => $current?->html_content,
                'text_content' => $current?->text_content,
                'sms_content' => $current?->sms_content,
                'variables_schema' => $current?->variables_schema,
                'metadata' => $current?->metadata,
                'created_by' => $userId,
            ]);

            $clone->update(['current_version_id' => $version->id]);

            Log::info("Template duplicado.", [
                'action' => 'template.duplicated',
                'original_id' => $template->id,
                'clone_id' => $clone->id,
                'user_id' => $userId,
            ]);

            return $clone->fresh(['currentVersion', 'creator', 'updater']);
        });
    }

    /**
     * Cria explicitamente uma nova versão em DRAFT para o template.
     */
    public function createVersion(Template $template, array $data, ?int $userId = null): TemplateVersion
    {
        $this->validateChannelVariables($template->channel, $data);

        return DB::transaction(function () use ($template, $data, $userId) {
            $maxVersion = TemplateVersion::where('template_id', $template->id)->max('version') ?? 0;
            $newVersionNumber = $maxVersion + 1;

            $versionData = $this->prepareVersionContent($template->channel, $data, $userId, $newVersionNumber);
            $versionData['template_id'] = $template->id;
            $versionData['status'] = strtoupper($data['status'] ?? 'DRAFT');

            $version = TemplateVersion::create($versionData);

            Log::info("Nova versão criada explicitamente.", [
                'action' => 'template.version.created',
                'template_id' => $template->id,
                'version' => $newVersionNumber,
                'user_id' => $userId,
            ]);

            return $version;
        });
    }

    /**
     * Publica uma versão específica: torna-a PUBLISHED e define-a como current_version do template.
     */
    public function publishVersion(Template $template, int $versionNumber, ?int $userId = null): Template
    {
        $version = TemplateVersion::where('template_id', $template->id)
            ->where('version', $versionNumber)
            ->first();

        if (!$version) {
            throw new InvalidArgumentException("Versão {$versionNumber} não encontrada para este template.");
        }

        return DB::transaction(function () use ($template, $version, $userId) {
            $version->update(['status' => 'PUBLISHED']);

            $template->update([
                'current_version_id' => $version->id,
                'status' => 'ACTIVE',
                'updated_by' => $userId,
            ]);

            Log::info("Versão do template publicada.", [
                'action' => 'template.published',
                'template_id' => $template->id,
                'version' => $version->version,
                'user_id' => $userId,
            ]);

            return $template->fresh(['currentVersion', 'creator', 'updater']);
        });
    }

    /**
     * Restaura uma versão histórica: cria uma nova versão N+1 com o conteúdo histórico,
     * preservando a integridade e auditoria de versões passadas.
     */
    public function restoreVersion(Template $template, int $versionNumber, ?int $userId = null): Template
    {
        $historical = TemplateVersion::where('template_id', $template->id)
            ->where('version', $versionNumber)
            ->first();

        if (!$historical) {
            throw new InvalidArgumentException("Versão {$versionNumber} não encontrada para restauração.");
        }

        return DB::transaction(function () use ($template, $historical, $userId) {
            $maxVersion = TemplateVersion::where('template_id', $template->id)->max('version') ?? 1;
            $newVersionNumber = $maxVersion + 1;

            $version = TemplateVersion::create([
                'uuid' => (string) Str::uuid(),
                'template_id' => $template->id,
                'version' => $newVersionNumber,
                'status' => 'PUBLISHED',
                'subject' => $historical->subject,
                'preheader' => $historical->preheader,
                'html_content' => $historical->html_content,
                'text_content' => $historical->text_content,
                'sms_content' => $historical->sms_content,
                'variables_schema' => $historical->variables_schema,
                'metadata' => array_merge($historical->metadata ?? [], [
                    'restored_from_version' => $historical->version,
                ]),
                'created_by' => $userId,
            ]);

            $template->update([
                'current_version_id' => $version->id,
                'status' => 'ACTIVE',
                'updated_by' => $userId,
            ]);

            Log::info("Versão do template restaurada como nova versão publicada.", [
                'action' => 'template.version.restored',
                'template_id' => $template->id,
                'from_version' => $historical->version,
                'new_version' => $newVersionNumber,
                'user_id' => $userId,
            ]);

            return $template->fresh(['currentVersion', 'creator', 'updater']);
        });
    }

    /**
     * Lista o histórico paginado de versões de um template.
     */
    public function listVersions(Template $template, int $perPage = 15): LengthAwarePaginator
    {
        return TemplateVersion::where('template_id', $template->id)
            ->with(['creator:id,name,email'])
            ->orderBy('version', 'desc')
            ->paginate($perPage);
    }

    /**
     * Renderiza o preview do template com dados contextuais fornecidos ou padrão simulado.
     */
    public function preview(Template $template, array $sampleData = [], ?int $versionNumber = null, array $overrideContent = []): array
    {
        $version = null;
        if ($versionNumber) {
            $version = TemplateVersion::where('template_id', $template->id)
                ->where('version', $versionNumber)
                ->first();
        }

        if (!$version) {
            $version = $template->currentVersion;
        }

        // Mock padrão seguro se não houver contexto fornecido (LGPD: sem dados reais)
        $context = array_merge([
            'player' => [
                'name' => 'Carlos Eduardo Santos',
                'first_name' => 'Carlos',
                'email' => 'carlos.santos@email.com',
                'phone' => '5531998877661',
                'city' => 'Belo Horizonte',
                'state' => 'MG',
                'status' => 'ACTIVE',
                'external_id' => 'PLY-1001',
                'created_at' => '01/01/2026',
                'deposit' => [
                    'total' => 1500.00,
                    'count' => 5,
                    'last_amount' => 150.00,
                ],
                'bet' => [
                    'total' => 3200.00,
                    'count' => 42,
                    'last_amount' => 50.00,
                ],
            ],
            'platform' => [
                'name' => $template->platform->name ?? 'Bet Brasil',
                'slug' => $template->platform->slug ?? 'bet-brasil',
            ],
            'segment' => [
                'name' => 'VIPs Ativos',
            ],
        ], $sampleData);

        if ($template->isEmail()) {
            $subject = $overrideContent['subject'] ?? $version?->subject ?? '';
            $preheader = $overrideContent['preheader'] ?? $version?->preheader ?? '';
            $html = $overrideContent['html_content'] ?? $version?->html_content ?? '';
            $text = $overrideContent['text_content'] ?? $version?->text_content ?? '';

            return $this->renderer->renderEmail($subject, $preheader, $html, $text, $context);
        } else {
            $sms = $overrideContent['sms_content'] ?? $version?->sms_content ?? '';
            return $this->renderer->renderSms($sms, $context);
        }
    }

    /**
     * Valida sintaxe e permissões das variáveis de template contra o canal.
     */
    protected function validateChannelVariables(string $channel, array $data): void
    {
        $fieldsToCheck = [
            'subject' => $data['subject'] ?? null,
            'preheader' => $data['preheader'] ?? null,
            'html_content' => $data['html_content'] ?? null,
            'text_content' => $data['text_content'] ?? null,
            'sms_content' => $data['sms_content'] ?? null,
        ];

        foreach ($fieldsToCheck as $field => $content) {
            if (!$content) continue;

            $validation = TemplateVariableRegistry::validateContentVariables($content, $channel);
            if (!$validation['is_valid']) {
                $err = implode(' ', $validation['errors']);
                throw new InvalidArgumentException("Erro na validação de variáveis do campo '{$field}': {$err}");
            }
        }
    }

    /**
     * Prepara, sanitiza e extrai schema de variáveis para gravação na versão.
     */
    protected function prepareVersionContent(string $channel, array $data, ?int $userId, int $versionNumber): array
    {
        $subject = isset($data['subject']) ? trim($data['subject']) : null;
        $preheader = isset($data['preheader']) ? trim($data['preheader']) : null;
        $htmlContent = isset($data['html_content']) ? $this->sanitizer->sanitizeHtml($data['html_content']) : null;
        $textContent = isset($data['text_content']) ? $this->sanitizer->sanitizeText($data['text_content']) : null;
        $smsContent = isset($data['sms_content']) ? $this->sanitizer->sanitizeText($data['sms_content']) : null;

        // Extrai todas as variáveis para schema auditável
        $allVars = [];
        foreach ([$subject, $preheader, $htmlContent, $textContent, $smsContent] as $str) {
            if ($str) {
                $extracted = TemplateVariableRegistry::extractVariables($str);
                foreach ($extracted as $item) {
                    $allVars[$item['key']] = $item;
                }
            }
        }

        return [
            'uuid' => (string) Str::uuid(),
            'version' => $versionNumber,
            'subject' => $subject,
            'preheader' => $preheader,
            'html_content' => $htmlContent,
            'text_content' => $textContent,
            'sms_content' => $smsContent,
            'variables_schema' => array_values($allVars),
            'metadata' => [
                'sanitized' => true,
                'char_count' => $smsContent ? mb_strlen($smsContent, 'UTF-8') : null,
            ],
            'created_by' => $userId,
        ];
    }
}

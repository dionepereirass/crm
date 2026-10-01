<?php

namespace App\Services\Privacy;

class ConsentEvidenceService
{
    public function __construct(
        protected SensitiveDataSanitizer $sanitizer
    ) {}

    /**
     * Constrói a estrutura canônica de evidência de consentimento (Art. 8º da LGPD).
     */
    public function buildEvidence(array $params): array
    {
        $sanitizedMetadata = isset($params['metadata']) && is_array($params['metadata'])
            ? $this->sanitizer->sanitize($params['metadata'])
            : [];

        $evidence = [
            'player_id' => $params['player_id'] ?? null,
            'platform_id' => $params['platform_id'] ?? null,
            'type' => $params['type'] ?? 'MARKETING_EMAIL',
            'previous_status' => $params['previous_status'] ?? null,
            'new_status' => $params['new_status'] ?? 'GRANTED',
            'version' => $params['version'] ?? 'v1.0',
            'source' => $params['source'] ?? 'api',
            'ip_address' => $params['ip_address'] ?? null,
            'user_agent' => $params['user_agent'] ?? null,
            'timestamp' => now()->toIso8601String(),
            'metadata' => $sanitizedMetadata,
        ];

        return $evidence;
    }

    /**
     * Gera o hash SHA-256 da evidência para detecção de adulteração.
     */
    public function generateHash(array $evidence): string
    {
        // Garante ordenação canônica de chaves antes do hash
        ksort($evidence);
        $canonicalJson = json_encode($evidence, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return hash('sha256', $canonicalJson);
    }

    /**
     * Valida a integridade do hash da evidência.
     */
    public function verifyIntegrity(array $evidence, string $expectedHash): bool
    {
        $computedHash = $this->generateHash($evidence);
        return hash_equals($expectedHash, $computedHash);
    }
}

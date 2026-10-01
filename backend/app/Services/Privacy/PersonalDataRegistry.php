<?php

namespace App\Services\Privacy;

class PersonalDataRegistry
{
    /**
     * Categorias de dados pessoais e respectivos campos sob a LGPD.
     */
    protected const CATEGORIES = [
        'IDENTIFICATION' => [
            'name' => 'Identificação Pessoal',
            'fields' => ['name', 'first_name', 'last_name', 'cpf', 'birth_date'],
            'is_sensitive' => true,
        ],
        'CONTACT' => [
            'name' => 'Dados de Contato',
            'fields' => ['email', 'phone', 'whatsapp'],
            'is_sensitive' => true,
        ],
        'LOCATION' => [
            'name' => 'Dados de Localização',
            'fields' => ['address', 'city', 'state', 'postal_code', 'zip_code', 'country', 'ip_address'],
            'is_sensitive' => false,
        ],
        'FINANCIAL' => [
            'name' => 'Dados Financeiros Transacionais',
            'fields' => ['deposit_data', 'withdrawal_data', 'transaction_metadata', 'payment_method'],
            'is_sensitive' => true,
        ],
        'BEHAVIORAL' => [
            'name' => 'Dados Comportamentais & Gaming',
            'fields' => ['login_history', 'betting_activity', 'campaign_interaction', 'automation_interaction'],
            'is_sensitive' => false,
        ],
        'TECHNICAL' => [
            'name' => 'Dados Técnicos & Dispositivo',
            'fields' => ['user_agent', 'device', 'browser', 'tracking_identifiers', 'session_id'],
            'is_sensitive' => false,
        ],
    ];

    /**
     * Retorna todas as categorias e metadados.
     */
    public function getAllCategories(): array
    {
        return self::CATEGORIES;
    }

    /**
     * Retorna a lista de campos de uma categoria específica.
     */
    public function getFields(string $category): array
    {
        $upper = strtoupper($category);
        return self::CATEGORIES[$upper]['fields'] ?? [];
    }

    /**
     * Verifica se um determinado campo é considerado pessoal/LGPD.
     */
    public function isPersonalField(string $field): bool
    {
        $normalized = strtolower(trim($field));
        foreach (self::CATEGORIES as $cat) {
            if (in_array($normalized, $cat['fields'], true)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Identifica a categoria a que pertence um campo.
     */
    public function getCategoryForField(string $field): ?string
    {
        $normalized = strtolower(trim($field));
        foreach (self::CATEGORIES as $catKey => $cat) {
            if (in_array($normalized, $cat['fields'], true)) {
                return $catKey;
            }
        }
        return null;
    }

    /**
     * Retorna todos os campos marcados como sensíveis.
     */
    public function getSensitiveFields(): array
    {
        $fields = [];
        foreach (self::CATEGORIES as $cat) {
            if ($cat['is_sensitive']) {
                $fields = array_merge($fields, $cat['fields']);
            }
        }
        return array_unique($fields);
    }
}

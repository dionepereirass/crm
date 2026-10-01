<?php

namespace App\Services\Providers;

use App\DTOs\Providers\ProviderHealthResult;
use App\Models\Provider;
use App\Models\ProviderCredential;
use App\Models\ProviderLog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ProviderService
{
    public function __construct(
        protected MessageProviderResolver $resolver
    ) {}

    /**
     * Lista provedores com paginação e filtros da plataforma ativa.
     */
    public function list(int $platformId, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Provider::where('platform_id', $platformId)
            ->withCount('messages');

        if (!empty($filters['channel']) && $filters['channel'] !== 'ALL') {
            $query->where('channel', strtoupper($filters['channel']));
        }

        if (!empty($filters['status']) && $filters['status'] !== 'ALL') {
            $query->where('status', strtoupper($filters['status']));
        }

        if (!empty($filters['driver'])) {
            $query->where('driver', strtolower($filters['driver']));
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('driver', 'LIKE', "%{$search}%");
            });
        }

        return $query->orderBy('priority', 'asc')->paginate($perPage);
    }

    /**
     * Busca um provedor por ID ou UUID.
     */
    public function getById(int|string $id, int $platformId): Provider
    {
        $query = Provider::where('platform_id', $platformId)
            ->with('credentials');

        if (is_numeric($id)) {
            $provider = $query->where('id', (int) $id)->first();
        } else {
            $provider = $query->where('uuid', $id)->first();
        }

        if (!$provider) {
            throw new InvalidArgumentException("Provedor não encontrado para a plataforma atual.");
        }

        return $provider;
    }

    /**
     * Cria um novo provedor e salva suas credenciais criptografadas separadamente.
     */
    public function create(int $platformId, array $data): Provider
    {
        $channel = strtoupper($data['channel'] ?? 'EMAIL');
        $driver = strtolower($data['driver'] ?? 'fake_email');

        if (!ProviderRegistry::has($driver)) {
            throw new InvalidArgumentException("O driver [{$driver}] não é suportado pelo sistema.");
        }

        return DB::transaction(function () use ($platformId, $data, $channel, $driver) {
            $isDefault = (bool) ($data['is_default'] ?? false);

            if ($isDefault) {
                Provider::where('platform_id', $platformId)
                    ->where('channel', $channel)
                    ->update(['is_default' => false]);
            }

            $provider = Provider::create([
                'platform_id' => $platformId,
                'name' => $data['name'],
                'channel' => $channel,
                'driver' => $driver,
                'status' => strtoupper($data['status'] ?? 'ACTIVE'),
                'is_default' => $isDefault,
                'priority' => (int) ($data['priority'] ?? 1),
                'is_fallback' => (bool) ($data['is_fallback'] ?? false),
                'rate_limit_per_minute' => (int) ($data['rate_limit_per_minute'] ?? 60),
                'configuration' => $data['configuration'] ?? [],
            ]);

            // Salva credenciais criptografadas separadamente se fornecidas
            $credentials = [];
            if (!empty($data['api_key'])) {
                $credentials['api_key'] = trim($data['api_key']);
            }
            if (!empty($data['api_token'])) {
                $credentials['api_token'] = trim($data['api_token']);
            }
            if (!empty($data['credentials']) && is_array($data['credentials'])) {
                $credentials = array_merge($credentials, $data['credentials']);
            }

            if (!empty($credentials)) {
                $credModel = new ProviderCredential(['provider_id' => $provider->id]);
                $credModel->setCredentials($credentials);
                $credModel->save();
            }

            return $provider->fresh(['credentials']);
        });
    }

    /**
     * Atualiza metadados e credenciais de um provedor.
     */
    public function update(int|string $id, int $platformId, array $data): Provider
    {
        $provider = $this->getById($id, $platformId);

        return DB::transaction(function () use ($provider, $data, $platformId) {
            if (isset($data['is_default']) && $data['is_default']) {
                Provider::where('platform_id', $platformId)
                    ->where('channel', $provider->channel)
                    ->where('id', '!=', $provider->id)
                    ->update(['is_default' => false]);
            }

            $updateData = [];
            foreach (['name', 'status', 'is_default', 'priority', 'is_fallback', 'rate_limit_per_minute', 'configuration'] as $field) {
                if (array_key_exists($field, $data)) {
                    $updateData[$field] = $data[$field];
                }
            }

            if (!empty($updateData)) {
                $provider->update($updateData);
            }

            // Atualiza credenciais se uma nova chave/token for submetida
            $credentials = [];
            if (!empty($data['api_key'])) {
                $credentials['api_key'] = trim($data['api_key']);
            }
            if (!empty($data['api_token'])) {
                $credentials['api_token'] = trim($data['api_token']);
            }
            if (!empty($data['credentials']) && is_array($data['credentials'])) {
                $credentials = array_merge($credentials, $data['credentials']);
            }

            if (!empty($credentials)) {
                $credModel = $provider->credentials ?: new ProviderCredential(['provider_id' => $provider->id]);
                $credModel->setCredentials($credentials);
                $credModel->save();
            }

            return $provider->fresh(['credentials']);
        });
    }

    /**
     * Remove um provedor.
     */
    public function delete(int|string $id, int $platformId): void
    {
        $provider = $this->getById($id, $platformId);
        $provider->delete();
    }

    /**
     * Ativa o provedor.
     */
    public function activate(int|string $id, int $platformId): Provider
    {
        $provider = $this->getById($id, $platformId);
        $provider->update(['status' => 'ACTIVE']);
        return $provider->fresh();
    }

    /**
     * Desativa o provedor.
     */
    public function deactivate(int|string $id, int $platformId): Provider
    {
        $provider = $this->getById($id, $platformId);
        $provider->update(['status' => 'INACTIVE']);
        return $provider->fresh();
    }

    /**
     * Executa teste de conectividade e validação de configuração (Health Check).
     */
    public function healthCheck(int|string $id, int $platformId): ProviderHealthResult
    {
        $provider = $this->getById($id, $platformId);
        $driver = $this->resolver->instantiateDriver($provider);

        $startTime = hrtime(true);
        $health = $driver->validateConfiguration();
        $latencyMs = $health->latencyMs ?: (int) round((hrtime(true) - $startTime) / 1e6);

        // Registra log do health check
        ProviderLog::create([
            'platform_id' => $platformId,
            'provider_id' => $provider->id,
            'channel' => $provider->channel,
            'action' => 'health_check',
            'status' => $health->isHealthy() ? 'SUCCESS' : 'FAILED',
            'error_message' => $health->error,
            'latency_ms' => $latencyMs,
            'request_metadata' => ['driver' => $provider->driver],
            'response_metadata' => $health->toArray(),
            'created_at' => now(),
        ]);

        if (!$health->isHealthy() && $provider->status === 'ACTIVE') {
            $provider->update(['status' => 'ERROR']);
        } elseif ($health->isHealthy() && $provider->status === 'ERROR') {
            $provider->update(['status' => 'ACTIVE']);
        }

        return $health;
    }
}

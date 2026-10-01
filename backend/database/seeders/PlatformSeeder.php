<?php

namespace Database\Seeders;

use App\Models\Platform;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class PlatformSeeder extends Seeder
{
    public function run(): void
    {
        $platforms = [
            [
                'name' => 'Bet Brasil',
                'slug' => 'bet-brasil',
                'status' => 'ACTIVE',
                'api_key' => 'pk_live_betbrasil_998877665544332211',
                'webhook_secret' => 'whsec_betbrasil_secure_hash_secret_key',
                'settings' => [
                    'currency' => 'BRL',
                    'timezone' => 'America/Sao_Paulo',
                    'support_email' => 'suporte@betbrasil.example.com',
                ],
            ],
            [
                'name' => 'Bet Global',
                'slug' => 'bet-global',
                'status' => 'ACTIVE',
                'api_key' => 'pk_live_betglobal_112233445566778899',
                'webhook_secret' => 'whsec_betglobal_secure_hash_secret_key',
                'settings' => [
                    'currency' => 'USD',
                    'timezone' => 'UTC',
                    'support_email' => 'support@betglobal.example.com',
                ],
            ],
        ];

        foreach ($platforms as $data) {
            Platform::updateOrCreate(
                ['slug' => $data['slug']],
                [
                    'uuid' => (string) Str::uuid(),
                    'name' => $data['name'],
                    'status' => $data['status'],
                    'api_key' => $data['api_key'],
                    'webhook_secret' => $data['webhook_secret'],
                    'settings' => $data['settings'],
                ]
            );
        }
    }
}

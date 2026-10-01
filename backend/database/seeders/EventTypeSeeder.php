<?php

namespace Database\Seeders;

use App\Models\EventType;
use App\Models\Platform;
use Illuminate\Database\Seeder;

class EventTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            [
                'key' => 'PLAYER_CREATED',
                'name' => 'Cadastro de Jogador',
                'description' => 'Disparado quando uma nova conta de apostador é registrada no operador.',
            ],
            [
                'key' => 'PLAYER_UPDATED',
                'name' => 'Atualização de Jogador',
                'description' => 'Disparado quando dados cadastrais ou perfil do apostador são alterados.',
            ],
            [
                'key' => 'DEPOSIT_SUCCESS',
                'name' => 'Depósito Confirmado',
                'description' => 'Disparado quando um depósito de fundos é liquidado com sucesso.',
            ],
            [
                'key' => 'BET_PLACED',
                'name' => 'Aposta Realizada',
                'description' => 'Disparado quando um bilhete de aposta esportiva ou rodada de cassino é submetida.',
            ],
            [
                'key' => 'BET_SETTLED',
                'name' => 'Aposta Liquidada',
                'description' => 'Disparado quando um bilhete de aposta é finalizado como ganho, perdido ou anulado.',
            ],
            [
                'key' => 'WITHDRAWAL_SUCCESS',
                'name' => 'Saque Concluído',
                'description' => 'Disparado quando uma retirada de fundos é aprovada e paga ao apostador.',
            ],
            [
                'key' => 'LOGIN',
                'name' => 'Login do Jogador',
                'description' => 'Disparado a cada autenticação bem-sucedida do apostador na plataforma.',
            ],
        ];

        // Seed global default event types (platform_id = null)
        foreach ($types as $type) {
            EventType::updateOrCreate(
                ['platform_id' => null, 'key' => $type['key']],
                [
                    'name' => $type['name'],
                    'description' => $type['description'],
                    'active' => true,
                ]
            );
        }
    }
}

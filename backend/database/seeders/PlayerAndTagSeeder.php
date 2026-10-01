<?php

namespace Database\Seeders;

use App\Models\Consent;
use App\Models\Platform;
use App\Models\Player;
use App\Models\Tag;
use Illuminate\Database\Seeder;

class PlayerAndTagSeeder extends Seeder
{
    public function run(): void
    {
        $betBrasil = Platform::where('slug', 'bet-brasil')->first();
        $betGlobal = Platform::where('slug', 'bet-global')->first();

        if (!$betBrasil) return;

        // 1. Tags para Bet Brasil
        $tagsBrasil = [
            'VIP' => ['color' => '#f59e0b', 'description' => 'Apostadores de alto volume de depósito'],
            'NOVO' => ['color' => '#10b981', 'description' => 'Cadastrados nos últimos 7 dias'],
            'INATIVO' => ['color' => '#ef4444', 'description' => 'Sem login há mais de 30 dias'],
            'ALTO_VALOR' => ['color' => '#8b5cf6', 'description' => 'GGR elevado'],
            'AFILIADO_VIP' => ['color' => '#3b82f6', 'description' => 'Origem via canal de afiliados prioritários'],
        ];

        $tagModelsBrasil = [];
        foreach ($tagsBrasil as $name => $meta) {
            $tagModelsBrasil[$name] = Tag::updateOrCreate(
                ['platform_id' => $betBrasil->id, 'name' => $name],
                [
                    'color' => $meta['color'],
                    'description' => $meta['description'],
                ]
            );
        }

        // Tags para Bet Global
        if ($betGlobal) {
            Tag::updateOrCreate(
                ['platform_id' => $betGlobal->id, 'name' => 'GLOBAL_VIP'],
                ['color' => '#f59e0b', 'description' => 'Global High Roller']
            );
        }

        // 2. Players para Bet Brasil
        $playersData = [
            [
                'external_id' => 'PLY-1001',
                'name' => 'Carlos Eduardo Santos',
                'email' => 'carlos.santos@email.com',
                'phone' => '5531998877661',
                'whatsapp' => '5531998877661',
                'cpf' => '12345678901',
                'birth_date' => '1988-04-12',
                'gender' => 'M',
                'city' => 'Belo Horizonte',
                'state' => 'MG',
                'status' => 'ACTIVE',
                'source' => 'afiliados_instagram',
                'affiliate' => 'afiliado_top_br',
                'promo_code' => 'BEMVINDO100',
                'custom_fields' => [
                    'nivel_vip' => 'Ouro',
                    'gerente' => 'Mariana Lima',
                    'observacao_marketing' => 'Apostador frequente no futebol nacional',
                ],
                'tags' => ['VIP', 'ALTO_VALOR'],
            ],
            [
                'external_id' => 'PLY-1002',
                'name' => 'Ana Beatriz Oliveira',
                'email' => 'ana.beatriz@email.com',
                'phone' => '5511987654321',
                'whatsapp' => '5511987654321',
                'cpf' => '98765432109',
                'birth_date' => '1995-09-23',
                'gender' => 'F',
                'city' => 'São Paulo',
                'state' => 'SP',
                'status' => 'ACTIVE',
                'source' => 'organico_google',
                'affiliate' => null,
                'promo_code' => null,
                'custom_fields' => [
                    'nivel_vip' => 'Prata',
                    'origem_cadastro' => 'landing_page_cassino',
                ],
                'tags' => ['NOVO'],
            ],
            [
                'external_id' => 'PLY-1003',
                'name' => 'Lucas Ferreira',
                'email' => 'lucas.ferreira@email.com',
                'phone' => '5521976543210',
                'whatsapp' => '5521976543210',
                'cpf' => '55544433322',
                'birth_date' => '1990-12-05',
                'gender' => 'M',
                'city' => 'Rio de Janeiro',
                'state' => 'RJ',
                'status' => 'INACTIVE',
                'source' => 'influencer_youtube',
                'affiliate' => 'canal_apostas',
                'promo_code' => 'YOUTUBE20',
                'custom_fields' => [
                    'nivel_vip' => 'Bronze',
                    'ultimo_bonus' => 'R$ 50,00',
                ],
                'tags' => ['INATIVO'],
            ],
            [
                'external_id' => 'PLY-1004',
                'name' => 'Mariana Souza Alves',
                'email' => 'mariana.alves@email.com',
                'phone' => '5541991234567',
                'whatsapp' => '5541991234567',
                'cpf' => '88877766655',
                'birth_date' => '1985-02-18',
                'gender' => 'F',
                'city' => 'Curitiba',
                'state' => 'PR',
                'status' => 'BLOCKED',
                'source' => 'direto',
                'affiliate' => null,
                'promo_code' => null,
                'custom_fields' => [
                    'motivo_bloqueio' => 'Autoexclusão solicitada pelo jogador',
                ],
                'tags' => [],
            ],
        ];

        foreach ($playersData as $item) {
            $tagNames = $item['tags'];
            unset($item['tags']);

            $player = Player::updateOrCreate(
                [
                    'platform_id' => $betBrasil->id,
                    'external_id' => $item['external_id'],
                ],
                $item
            );

            // Vincula Tags
            $attachIds = [];
            foreach ($tagNames as $tName) {
                if (isset($tagModelsBrasil[$tName])) {
                    $attachIds[] = $tagModelsBrasil[$tName]->id;
                }
            }
            if (!empty($attachIds)) {
                $player->tags()->syncWithoutDetaching($attachIds);
            }

            // Cria Consentimentos iniciais (LGPD)
            Consent::updateOrCreate(
                ['player_id' => $player->id, 'channel' => 'EMAIL'],
                [
                    'is_granted' => $player->status !== 'BLOCKED',
                    'consent_date' => now(),
                    'consent_source' => 'registration_form',
                    'consent_version' => 'v1.0_terms_lgpd',
                ]
            );
            Consent::updateOrCreate(
                ['player_id' => $player->id, 'channel' => 'SMS'],
                [
                    'is_granted' => $player->status !== 'BLOCKED',
                    'consent_date' => now(),
                    'consent_source' => 'registration_form',
                    'consent_version' => 'v1.0_terms_lgpd',
                ]
            );
        }

        // 3. Jogador na Bet Global com o MESMO external_id (PLY-1001) para validar que o mesmo ID existe em plataformas diferentes
        if ($betGlobal) {
            Player::updateOrCreate(
                [
                    'platform_id' => $betGlobal->id,
                    'external_id' => 'PLY-1001',
                ],
                [
                    'name' => 'John Doe (Global)',
                    'email' => 'john.doe@globalexample.com',
                    'phone' => '14155552671',
                    'status' => 'ACTIVE',
                    'source' => 'global_campaign',
                ]
            );
        }
    }
}

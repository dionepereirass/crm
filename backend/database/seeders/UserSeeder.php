<?php

namespace Database\Seeders;

use App\Models\Platform;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $betBrasil = Platform::where('slug', 'bet-brasil')->first();
        $betGlobal = Platform::where('slug', 'bet-global')->first();

        $defaultPassword = Hash::make('Secret@123456');

        // 1. SUPER_ADMIN
        $superAdmin = User::updateOrCreate(
            ['email' => 'superadmin@crm.example.com'],
            [
                'name' => 'Super Administrador',
                'password' => $defaultPassword,
                'status' => 'ACTIVE',
                'two_factor_confirmed_at' => now(),
            ]
        );
        $superAdmin->assignRole('SUPER_ADMIN');

        // 2. ADMIN (Vinculado a Bet Brasil)
        $admin = User::updateOrCreate(
            ['email' => 'admin@crm.example.com'],
            [
                'name' => 'Administrador Bet Brasil',
                'password' => $defaultPassword,
                'status' => 'ACTIVE',
            ]
        );
        $admin->assignRole('ADMIN');
        if ($betBrasil) {
            $admin->platforms()->syncWithoutDetaching([$betBrasil->id]);
        }

        // 3. MARKETING (Vinculado a Bet Brasil)
        $marketing = User::updateOrCreate(
            ['email' => 'marketing@crm.example.com'],
            [
                'name' => 'Especialista de Marketing',
                'password' => $defaultPassword,
                'status' => 'ACTIVE',
            ]
        );
        $marketing->assignRole('MARKETING');
        if ($betBrasil) {
            $marketing->platforms()->syncWithoutDetaching([$betBrasil->id]);
        }

        // 4. SUPPORT (Vinculado a Bet Brasil)
        $support = User::updateOrCreate(
            ['email' => 'support@crm.example.com'],
            [
                'name' => 'Agente de Suporte',
                'password' => $defaultPassword,
                'status' => 'ACTIVE',
            ]
        );
        $support->assignRole('SUPPORT');
        if ($betBrasil) {
            $support->platforms()->syncWithoutDetaching([$betBrasil->id]);
        }

        // 5. ANALYST (Vinculado a Bet Brasil e Bet Global)
        $analyst = User::updateOrCreate(
            ['email' => 'analyst@crm.example.com'],
            [
                'name' => 'Analista de Dados & BI',
                'password' => $defaultPassword,
                'status' => 'ACTIVE',
            ]
        );
        $analyst->assignRole('ANALYST');
        if ($betBrasil && $betGlobal) {
            $analyst->platforms()->syncWithoutDetaching([$betBrasil->id, $betGlobal->id]);
        }

        // 6. INACTIVE User (Para testes de bloqueio)
        $inactive = User::updateOrCreate(
            ['email' => 'inactive@crm.example.com'],
            [
                'name' => 'Usuário Desativado',
                'password' => $defaultPassword,
                'status' => 'INACTIVE',
            ]
        );
        $inactive->assignRole('SUPPORT');
        if ($betBrasil) {
            $inactive->platforms()->syncWithoutDetaching([$betBrasil->id]);
        }
    }
}

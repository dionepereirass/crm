<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class Player360Resource extends JsonResource
{
    /**
     * Transform the resource into a consolidated 360 profile.
     */
    public function toArray(Request $request): array
    {
        $canViewFullPii = $request->user()?->hasPermission('players.view') ?? false;

        // Structured timeline of available milestones in this phase
        $timeline = [];

        if ($this->created_at) {
            $timeline[] = [
                'event_type' => 'PLAYER_CREATED',
                'title' => 'Cadastro no CRM',
                'description' => "Jogador registrado no sistema via {$this->source}",
                'timestamp' => $this->created_at->toIso8601String(),
                'badge' => 'CRM',
            ];
        }

        if ($this->registered_at) {
            $timeline[] = [
                'event_type' => 'PLATFORM_REGISTER',
                'title' => 'Registro na Plataforma',
                'description' => "Conta criada na casa de aposta com external_id: {$this->external_id}",
                'timestamp' => $this->registered_at->toIso8601String(),
                'badge' => 'Operador',
            ];
        }

        if ($this->last_login_at) {
            $timeline[] = [
                'event_type' => 'LOGIN',
                'title' => 'Último Acesso Registrado',
                'description' => 'Acesso realizado pelo jogador',
                'timestamp' => $this->last_login_at->toIso8601String(),
                'badge' => 'Atividade',
            ];
        }

        // Include external events (deposits, bets, withdrawals, logins)
        $events = $this->relationLoaded('events') ? $this->events : $this->events()->with('eventType')->latest('occurred_at')->take(20)->get();
        foreach ($events as $ev) {
            $eventTypeKey = $ev->eventType?->key ?? 'EVENT';
            $amount = $ev->normalized_payload['data']['amount'] ?? null;
            $title = $ev->eventType?->name ?? $eventTypeKey;
            $desc = $title;
            if ($amount !== null) {
                $desc .= " — R$ " . number_format((float) $amount, 2, ',', '.');
            }

            $timeline[] = [
                'event_type' => $eventTypeKey,
                'title' => $title,
                'description' => $desc,
                'timestamp' => $ev->occurred_at ? $ev->occurred_at->toIso8601String() : $ev->created_at->toIso8601String(),
                'badge' => 'Webhook',
            ];
        }

        // Sort timeline descending
        usort($timeline, fn($a, $b) => strcmp($b['timestamp'], $a['timestamp']));

        return [
            'id' => $this->id,
            'external_id' => $this->external_id,
            'status' => $this->status,
            'personal_data' => [
                'name' => $this->name,
                'cpf' => $canViewFullPii ? $this->cpf : $this->masked_cpf,
                'birth_date' => $this->birth_date?->format('Y-m-d'),
                'gender' => $this->gender,
            ],
            'contact_data' => [
                'email' => $canViewFullPii ? $this->email : $this->masked_email,
                'phone' => $canViewFullPii ? $this->phone : $this->masked_phone,
                'whatsapp' => $canViewFullPii ? $this->whatsapp : $this->masked_phone,
                'city' => $this->city,
                'state' => $this->state,
                'zip_code' => $this->zip_code,
            ],
            'platform' => [
                'id' => $this->platform->id ?? $this->platform_id,
                'name' => $this->platform->name ?? 'Plataforma',
                'slug' => $this->platform->slug ?? '',
            ],
            'acquisition' => [
                'source' => $this->source,
                'affiliate' => $this->affiliate,
                'promo_code' => $this->promo_code,
                'registered_at' => $this->registered_at?->toIso8601String(),
                'created_at' => $this->created_at?->toIso8601String(),
            ],
            'activity' => [
                'last_login_at' => $this->last_login_at?->toIso8601String(),
                'last_activity_at' => $this->last_activity_at?->toIso8601String(),
                'days_since_creation' => $this->created_at ? (int) $this->created_at->diffInDays(now()) : 0,
                'days_since_last_login' => $this->last_login_at ? (int) $this->last_login_at->diffInDays(now()) : null,
            ],
            'tags' => TagResource::collection($this->whenLoaded('tags')),
            'custom_fields' => $this->custom_fields ?? [],
            'consents' => $this->consents->map(fn($c) => [
                'channel' => $c->channel,
                'is_granted' => $c->is_granted,
                'consent_date' => $c->consent_date?->toIso8601String(),
                'consent_source' => $c->consent_source,
                'consent_version' => $c->consent_version,
            ]),
            'timeline' => $timeline,
        ];
    }
}

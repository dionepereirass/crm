<?php

namespace App\Services\Campaigns;

use App\Models\Campaign;
use App\Models\Player;
use App\Services\Segments\SegmentQueryCompiler;
use Illuminate\Database\Eloquent\Builder;

class CampaignAudienceService
{
    public function __construct(
        protected SegmentQueryCompiler $queryCompiler
    ) {}

    /**
     * Calcula o breakdown completo de audiência e consentimento para uma campanha.
     */
    public function calculate(Campaign $campaign): array
    {
        $segment = $campaign->segment;
        if (!$segment) {
            return [
                'total_segment' => 0,
                'valid_contact' => 0,
                'with_consent' => 0,
                'blocked' => 0,
                'eligible' => 0,
                'sample' => [],
            ];
        }

        $platformId = $campaign->platform_id;
        $channel = strtoupper($campaign->channel);

        // 1. Query base do segmento compilado
        $baseQuery = $this->queryCompiler->compile($segment->rules_tree ?? [], $platformId);
        $totalSegment = (clone $baseQuery)->count();

        // 2. Contato válido (e-mail ou telefone preenchido)
        $contactQuery = clone $baseQuery;
        if ($channel === 'EMAIL') {
            $contactQuery->whereNotNull('email')->where('email', '!=', '');
        } else {
            $contactQuery->whereNotNull('phone')->where('phone', '!=', '');
        }
        $validContact = (clone $contactQuery)->count();

        // 3. Com consentimento de marketing ativo para o canal (LGPD)
        $consentQuery = (clone $contactQuery)->whereHas('consents', function ($q) use ($channel) {
            $q->where('channel', $channel)
              ->where('is_granted', true)
              ->whereNull('revoked_at');
        });
        $withConsent = (clone $consentQuery)->count();

        // 4. Bloqueados no segmento
        $blocked = (clone $baseQuery)->where('status', 'BLOCKED')->count();

        // 5. Total final elegível: contato válido + consentimento + status não bloqueado
        $eligibleQuery = (clone $consentQuery)->where('status', '!=', 'BLOCKED');
        $eligibleCount = (clone $eligibleQuery)->count();

        // Amostra segura para exibição (LGPD)
        $samplePlayers = (clone $eligibleQuery)
            ->limit(5)
            ->get();

        $sample = $samplePlayers->map(function (Player $p) use ($channel) {
            return [
                'id' => $p->id,
                'name' => $p->name,
                'recipient_masked' => $channel === 'EMAIL' ? $p->masked_email : $p->masked_phone,
                'status' => $p->status,
            ];
        })->toArray();

        return [
            'total_segment' => $totalSegment,
            'valid_contact' => $validContact,
            'with_consent' => $withConsent,
            'blocked' => $blocked,
            'eligible' => $eligibleCount,
            'sample' => $sample,
        ];
    }

    /**
     * Retorna a Query compilada de todos os jogadores do segmento.
     */
    public function getSegmentPlayersQuery(Campaign $campaign): Builder
    {
        $segment = $campaign->segment;
        if (!$segment) {
            return Player::query()->whereRaw('1 = 0');
        }

        return $this->queryCompiler->compile($segment->rules_tree ?? [], $campaign->platform_id);
    }

    /**
     * Retorna a Query restrita apenas aos jogadores 100% elegíveis para envio.
     */
    public function getEligibleQuery(Campaign $campaign): Builder
    {
        $channel = strtoupper($campaign->channel);
        $query = $this->getSegmentPlayersQuery($campaign);

        // Não bloqueados
        $query->where('status', '!=', 'BLOCKED');

        // Contato obrigatório
        if ($channel === 'EMAIL') {
            $query->whereNotNull('email')->where('email', '!=', '');
        } else {
            $query->whereNotNull('phone')->where('phone', '!=', '');
        }

        // Consentimento de marketing obrigatório
        $query->whereHas('consents', function ($q) use ($channel) {
            $q->where('channel', $channel)
              ->where('is_granted', true)
              ->whereNull('revoked_at');
        });

        return $query;
    }
}

<?php

namespace App\Services\Automations\Actions;

use App\Models\AutomationLog;
use App\Models\AutomationRun;
use App\Models\AutomationStep;
use App\Models\Tag;
use Illuminate\Support\Str;
use InvalidArgumentException;

class AddTagAction implements ActionHandlerInterface
{
    public function handle(AutomationRun $run, AutomationStep $step, array $config): array
    {
        $player = $run->player;
        if (!$player) {
            return [
                'status' => 'FAILED',
                'error' => 'Jogador não associado à execução da automação.',
            ];
        }

        $tagId = $config['tag_id'] ?? null;
        $tagName = $config['tag_name'] ?? null;

        if (!$tagId && !$tagName) {
            throw new InvalidArgumentException("ID ou nome da tag não informado na ação ADD_TAG.");
        }

        $tag = null;
        if ($tagId) {
            $tag = Tag::withoutGlobalScopes()
                ->where('platform_id', $run->platform_id)
                ->where('id', $tagId)
                ->first();
        } elseif ($tagName) {
            $tag = Tag::withoutGlobalScopes()->firstOrCreate(
                [
                    'platform_id' => $run->platform_id,
                    'name' => trim($tagName),
                ],
                [
                    'slug' => Str::slug($tagName),
                    'color' => $config['color'] ?? '#3B82F6',
                ]
            );
        }

        if (!$tag) {
            return [
                'status' => 'FAILED',
                'error' => "Tag não encontrada ou não pertence à plataforma {$run->platform_id}.",
            ];
        }

        // Adiciona a tag de forma idempotente
        $player->tags()->syncWithoutDetaching([$tag->id]);

        AutomationLog::log(
            $run->automation_id,
            'TAG_ATTACHED',
            "Tag '{$tag->name}' (#{$tag->id}) adicionada ao jogador #{$player->id} via automação.",
            'INFO',
            $run->id,
            $step->id,
            ['tag_id' => $tag->id, 'player_id' => $player->id]
        );

        return [
            'status' => 'COMPLETED',
            'tag_id' => $tag->id,
            'tag_name' => $tag->name,
            'player_id' => $player->id,
        ];
    }
}

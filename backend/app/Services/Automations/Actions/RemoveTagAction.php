<?php

namespace App\Services\Automations\Actions;

use App\Models\AutomationLog;
use App\Models\AutomationRun;
use App\Models\AutomationStep;
use App\Models\Tag;
use InvalidArgumentException;

class RemoveTagAction implements ActionHandlerInterface
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
            throw new InvalidArgumentException("ID ou nome da tag não informado na ação REMOVE_TAG.");
        }

        $tag = null;
        if ($tagId) {
            $tag = Tag::withoutGlobalScopes()
                ->where('platform_id', $run->platform_id)
                ->where('id', $tagId)
                ->first();
        } elseif ($tagName) {
            $tag = Tag::withoutGlobalScopes()
                ->where('platform_id', $run->platform_id)
                ->where('name', trim($tagName))
                ->first();
        }

        if (!$tag) {
            // Se a tag não existe, não gera erro fatal, registra SKIPPED
            return [
                'status' => 'SKIPPED',
                'reason' => 'TAG_NOT_FOUND',
                'message' => 'Tag não encontrada para remoção.',
            ];
        }

        $player->tags()->detach($tag->id);

        AutomationLog::log(
            $run->automation_id,
            'TAG_DETACHED',
            "Tag '{$tag->name}' (#{$tag->id}) removida do jogador #{$player->id} via automação.",
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

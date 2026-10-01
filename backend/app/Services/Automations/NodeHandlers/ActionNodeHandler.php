<?php

namespace App\Services\Automations\NodeHandlers;

use App\Models\AutomationLog;
use App\Models\AutomationNode;
use App\Models\AutomationRun;
use App\Models\AutomationStep;
use App\Services\Automations\Actions\AddTagAction;
use App\Services\Automations\Actions\EnterSegmentAction;
use App\Services\Automations\Actions\ExitSegmentAction;
use App\Services\Automations\Actions\RemoveTagAction;
use App\Services\Automations\Actions\SendEmailAction;
use App\Services\Automations\Actions\SendSmsAction;
use InvalidArgumentException;
use Throwable;

class ActionNodeHandler implements NodeHandlerInterface
{
    public function __construct(
        protected SendEmailAction $sendEmailAction,
        protected SendSmsAction $sendSmsAction,
        protected AddTagAction $addTagAction,
        protected RemoveTagAction $removeTagAction,
        protected EnterSegmentAction $enterSegmentAction,
        protected ExitSegmentAction $exitSegmentAction
    ) {}

    public function handle(AutomationRun $run, AutomationNode $node, AutomationStep $step): array
    {
        $config = $node->configuration ?? [];
        $actionType = strtoupper($config['action_type'] ?? '');

        if (empty($actionType)) {
            throw new InvalidArgumentException("Tipo de ação não especificado no nó #{$node->id}.");
        }

        try {
            $handler = match ($actionType) {
                'SEND_EMAIL' => $this->sendEmailAction,
                'SEND_SMS' => $this->sendSmsAction,
                'ADD_TAG' => $this->addTagAction,
                'REMOVE_TAG' => $this->removeTagAction,
                'ENTER_SEGMENT' => $this->enterSegmentAction,
                'EXIT_SEGMENT' => $this->exitSegmentAction,
                default => throw new InvalidArgumentException("Ação '{$actionType}' não suportada."),
            };

            $result = $handler->handle($run, $step, $config);

            $status = $result['status'] ?? 'COMPLETED';

            AutomationLog::log(
                $run->automation_id,
                'ACTION_EXECUTED',
                "Ação '{$actionType}' executada no nó #{$node->id} com status '{$status}'.",
                $status === 'FAILED' ? 'ERROR' : 'INFO',
                $run->id,
                $step->id,
                [
                    'action_type' => $actionType,
                    'result' => $result,
                ]
            );

            return [
                'status' => $status,
                'branch' => 'default',
                'output' => $result,
                'error' => $result['error'] ?? null,
            ];

        } catch (Throwable $e) {
            AutomationLog::log(
                $run->automation_id,
                'ACTION_ERROR',
                "Falha ao executar ação '{$actionType}' no nó #{$node->id}: {$e->getMessage()}",
                'ERROR',
                $run->id,
                $step->id,
                ['error' => $e->getMessage()]
            );

            return [
                'status' => 'FAILED',
                'branch' => 'default',
                'output' => ['error' => $e->getMessage()],
                'error' => $e->getMessage(),
            ];
        }
    }
}

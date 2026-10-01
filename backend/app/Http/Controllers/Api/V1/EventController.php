<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\EventProcessingStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\EventResource;
use App\Jobs\ProcessEventJob;
use App\Models\Event;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EventController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user->isSuperAdmin() && !$user->hasPermission('events.view')) {
            return response()->json([
                'success' => false,
                'message' => 'Você não tem permissão para visualizar eventos.',
            ], 403);
        }

        $query = Event::with(['eventType', 'player'])->latest('occurred_at');

        if ($request->filled('status')) {
            $query->where('processing_status', $request->status);
        }

        if ($request->filled('event_type')) {
            $query->whereHas('eventType', function ($q) use ($request) {
                $q->where('key', $request->event_type);
            });
        }

        if ($request->filled('player_id')) {
            $query->where('player_id', $request->player_id);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('external_event_id', 'like', "%{$search}%")
                  ->orWhere('uuid', 'like', "%{$search}%")
                  ->orWhereHas('player', function ($pq) use ($search) {
                      $pq->where('name', 'like', "%{$search}%")
                         ->orWhere('external_id', 'like', "%{$search}%");
                  });
            });
        }

        if ($request->filled('date_from')) {
            $query->where('occurred_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->where('occurred_at', '<=', $request->date_to);
        }

        $perPage = min(max((int) $request->input('per_page', 20), 1), 100);
        $events = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => EventResource::collection($events->items()),
            'meta' => [
                'current_page' => $events->currentPage(),
                'last_page' => $events->lastPage(),
                'per_page' => $events->perPage(),
                'total' => $events->total(),
            ],
            'links' => [
                'first' => $events->url(1),
                'last' => $events->url($events->lastPage()),
                'prev' => $events->previousPageUrl(),
                'next' => $events->nextPageUrl(),
            ],
        ]);
    }

    public function show(int $id, Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user->isSuperAdmin() && !$user->hasPermission('events.view')) {
            return response()->json([
                'success' => false,
                'message' => 'Você não tem permissão para visualizar eventos.',
            ], 403);
        }

        $event = Event::with(['eventType', 'player', 'webhookLogs'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => new EventResource($event),
        ]);
    }

    public function reprocess(int $id, Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user->isSuperAdmin() && !$user->hasPermission('events.reprocess')) {
            return response()->json([
                'success' => false,
                'message' => 'Você não tem permissão para reprocessar eventos.',
            ], 403);
        }

        $event = Event::findOrFail($id);

        // Atualiza status para QUEUED e limpa erro anterior
        $event->update([
            'processing_status' => EventProcessingStatus::QUEUED,
            'error_message' => null,
        ]);

        // Enfileira reprocessamento
        ProcessEventJob::dispatch($event->id)->onQueue('events');

        return response()->json([
            'success' => true,
            'message' => 'Evento reenfileirado para reprocessamento com sucesso.',
            'data' => new EventResource($event->fresh(['eventType', 'player'])),
        ]);
    }
}

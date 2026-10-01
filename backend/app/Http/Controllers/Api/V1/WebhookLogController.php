<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\WebhookLogResource;
use App\Models\WebhookLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WebhookLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user->isSuperAdmin() && !$user->hasPermission('webhooks.view')) {
            return response()->json([
                'success' => false,
                'message' => 'Você não tem permissão para visualizar logs de webhooks.',
            ], 403);
        }

        $query = WebhookLog::latest('received_at');

        if ($request->has('signature_valid') && $request->signature_valid !== '') {
            $query->where('signature_valid', filter_var($request->signature_valid, FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->filled('status')) {
            $query->where('processing_status', $request->status);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('external_event_id', 'like', "%{$search}%")
                  ->orWhere('endpoint', 'like', "%{$search}%")
                  ->orWhere('uuid', 'like', "%{$search}%");
            });
        }

        $perPage = min(max((int) $request->input('per_page', 20), 1), 100);
        $logs = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => WebhookLogResource::collection($logs->items()),
            'meta' => [
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
            ],
            'links' => [
                'first' => $logs->url(1),
                'last' => $logs->url($logs->lastPage()),
                'prev' => $logs->previousPageUrl(),
                'next' => $logs->nextPageUrl(),
            ],
        ]);
    }

    public function show(int $id, Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user->isSuperAdmin() && !$user->hasPermission('webhooks.view')) {
            return response()->json([
                'success' => false,
                'message' => 'Você não tem permissão para visualizar logs de webhooks.',
            ], 403);
        }

        $log = WebhookLog::with('event')->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => new WebhookLogResource($log),
        ]);
    }
}

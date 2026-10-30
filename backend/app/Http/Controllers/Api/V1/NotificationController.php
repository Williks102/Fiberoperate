<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

/** Notifications de l'utilisateur connecté : les siennes seulement. */
class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $page = $request->user()->notifications()->paginate(min(max($request->integer('per_page', 20), 1), 50));

        return response()->json([
            'data' => $page->getCollection()->map(fn (DatabaseNotification $n) => [
                'id' => $n->id,
                'title' => $n->data['title'] ?? '',
                'message' => $n->data['message'] ?? '',
                'link' => $n->data['link'] ?? null,
                'read' => $n->read_at !== null,
                'created_at' => $n->created_at?->toIso8601String(),
            ])->values(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'total' => $page->total(),
                'unread_count' => $request->user()->unreadNotifications()->count(),
            ],
        ]);
    }

    public function read(Request $request, string $notification): JsonResponse
    {
        $request->user()->notifications()->whereKey($notification)->firstOrFail()->markAsRead();

        return response()->json(['data' => ['id' => $notification, 'read' => true]]);
    }

    public function readAll(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return response()->json(['data' => ['unread_count' => 0]]);
    }
}

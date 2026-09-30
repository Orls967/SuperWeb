<?php

declare(strict_types=1);

namespace Modules\Core\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Modules\Core\Application\Services\NotificationService;
use Modules\Core\Domain\Models\PlatformNotification;

class NotificationController extends Controller
{
    public function __construct(
        private NotificationService $service,
    ) {}

    /**
     * Full notification page.
     */
    public function index(Request $request): View
    {
        $userId = $request->user()->id;

        $notifications = PlatformNotification::forUser($userId)
            ->latest()
            ->paginate(20);

        $unreadCount = $this->service->unreadCount($userId);

        return view('core::notifications.index', compact('notifications', 'unreadCount'));
    }

    /**
     * JSON endpoint for the bell dropdown (recent 8 + unread count).
     */
    public function recent(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        return response()->json([
            'unread_count' => $this->service->unreadCount($userId),
            'notifications' => $this->service->recent($userId, 8)->map(fn ($n) => [
                'id' => $n->id,
                'uuid' => $n->uuid,
                'type' => $n->type,
                'icon' => $n->icon,
                'title' => $n->title,
                'body' => $n->body,
                'action_url' => $n->action_url,
                'is_read' => $n->isRead(),
                'time_ago' => $n->created_at->diffForHumans(),
            ]),
        ]);
    }

    /**
     * Mark a single notification as read and redirect to its action_url.
     */
    public function read(Request $request, PlatformNotification $notification): RedirectResponse
    {
        abort_unless($notification->user_id === $request->user()->id, 403);

        $notification->markAsRead();

        return redirect($notification->action_url ?? route('notifications.index'));
    }

    /**
     * Mark all as read.
     */
    public function markAllRead(Request $request): RedirectResponse
    {
        $this->service->markAllRead($request->user()->id);

        return back()->with('success', 'Semua notifikasi telah dibaca.');
    }

    /**
     * JSON mark-all-read for the dropdown.
     */
    public function markAllReadJson(Request $request): JsonResponse
    {
        $count = $this->service->markAllRead($request->user()->id);

        return response()->json(['marked' => $count]);
    }
}

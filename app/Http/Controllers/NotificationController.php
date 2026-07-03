<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        abort_unless($user !== null, 403);

        $filters = [
            'search' => $request->string('search')->trim()->toString(),
            'read' => $request->string('read')->trim()->toString(),
            'category' => $request->string('category')->trim()->toString(),
            'date_from' => $request->string('date_from')->trim()->toString(),
            'date_to' => $request->string('date_to')->trim()->toString(),
        ];

        $query = $user->notifications()->latest();

        if ($filters['search'] !== '') {
            $query->where(function ($builder) use ($filters): void {
                $builder
                    ->where('data->title', 'like', "%{$filters['search']}%")
                    ->orWhere('data->message', 'like', "%{$filters['search']}%");
            });
        }

        match ($filters['read']) {
            'read' => $query->whereNotNull('read_at'),
            'unread' => $query->whereNull('read_at'),
            default => null,
        };

        if ($filters['category'] !== '') {
            $query->where('data->category', $filters['category']);
        }

        if ($filters['date_from'] !== '') {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if ($filters['date_to'] !== '') {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        $notifications = $query
            ->paginate(25)
            ->withQueryString()
            ->through(fn (DatabaseNotification $notification): array => $this->presentNotification($notification));

        $categories = $user->notifications()
            ->select('data')
            ->get()
            ->map(function (DatabaseNotification $notification): ?string {
                $data = is_array($notification->data) ? $notification->data : [];

                return is_string($data['category'] ?? null) ? $data['category'] : null;
            })
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();

        return Inertia::render('notifications/Index', [
            'filters' => $filters,
            'notifications' => $notifications,
            'categories' => $categories,
            'unreadCount' => $user->unreadNotifications()->count(),
        ]);
    }

    public function markAsRead(Request $request, string $notification): RedirectResponse
    {
        /** @var DatabaseNotification $record */
        $record = $request->user()
            ->notifications()
            ->whereKey($notification)
            ->firstOrFail();

        if ($record->read_at === null) {
            $record->markAsRead();
        }

        return redirect()->back(303);
    }

    public function markAllAsRead(Request $request): RedirectResponse
    {
        $request->user()
            ->unreadNotifications()
            ->update(['read_at' => now()]);

        return redirect()->back(303);
    }

    /**
     * @return array<string, mixed>
     */
    private function presentNotification(DatabaseNotification $notification): array
    {
        $data = is_array($notification->data) ? $notification->data : [];

        return [
            'id' => $notification->id,
            'type' => $notification->type,
            'category' => is_string($data['category'] ?? null) ? $data['category'] : 'general',
            'severity' => is_string($data['severity'] ?? null) ? $data['severity'] : 'info',
            'title' => is_string($data['title'] ?? null) ? $data['title'] : __('Notification'),
            'message' => is_string($data['message'] ?? null) ? $data['message'] : '',
            'url' => is_string($data['url'] ?? null) ? $data['url'] : null,
            'createdAt' => $notification->created_at?->toIso8601String(),
            'readAt' => $notification->read_at?->toIso8601String(),
            'data' => $data,
        ];
    }
}

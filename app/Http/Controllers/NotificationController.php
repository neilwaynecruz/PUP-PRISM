<?php

namespace App\Http\Controllers;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\DB;
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
                    ->whereLike($this->dataField('title'), "%{$filters['search']}%")
                    ->orWhereLike($this->dataField('message'), "%{$filters['search']}%");
            });
        }

        match ($filters['read']) {
            'read' => $query->whereNotNull('read_at'),
            'unread' => $query->whereNull('read_at'),
            default => null,
        };

        if ($filters['category'] !== '') {
            $query->where($this->dataField('category'), $filters['category']);
        }

        // Ignore malformed dates instead of letting the database reject the query.
        if ($this->isValidDate($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if ($this->isValidDate($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        $notifications = $query
            ->paginate(25)
            ->withQueryString()
            ->through(fn (DatabaseNotification $notification): array => $this->presentNotification($notification));

        $categoryExpression = $this->jsonStringExpression('category');

        $categories = $user->notifications()
            ->reorder()
            ->selectRaw("{$categoryExpression} as category")
            ->whereRaw("{$categoryExpression} IS NOT NULL")
            ->whereRaw("{$categoryExpression} <> ''")
            ->distinct()
            ->orderByRaw("{$categoryExpression} asc")
            ->pluck('category')
            ->filter(static fn (mixed $category): bool => is_string($category) && $category !== '')
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
     * A key inside the notification payload, usable in where clauses.
     *
     * Laravel stores `notifications.data` as text, which PostgreSQL's JSON operators
     * reject, so the column is cast to jsonb there. Other drivers read JSON paths from text.
     */
    private function dataField(string $key): Expression|string
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            return DB::raw("(data::jsonb ->> '{$key}')");
        }

        return "data->{$key}";
    }

    private function jsonStringExpression(string $key): string
    {
        return match (DB::connection()->getDriverName()) {
            'pgsql' => "(data::jsonb ->> '{$key}')",
            'sqlite' => "json_extract(data, '$.{$key}')",
            default => "CASE WHEN JSON_EXTRACT(data, '$.{$key}') IS NULL OR JSON_TYPE(JSON_EXTRACT(data, '$.{$key}')) = 'NULL' THEN NULL ELSE JSON_UNQUOTE(JSON_EXTRACT(data, '$.{$key}')) END",
        };
    }

    private function isValidDate(string $value): bool
    {
        return $value !== '' && CarbonImmutable::hasFormat($value, 'Y-m-d');
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

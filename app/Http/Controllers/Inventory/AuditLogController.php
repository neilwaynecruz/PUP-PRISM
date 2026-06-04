<?php

declare(strict_types=1);

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Services\AuditLog\AuditLogPresenter;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AuditLogController extends Controller
{
    public function __construct(
        private readonly AuditLogPresenter $presenter,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', AuditLog::class);

        $filters = [
            'search' => $request->string('search')->trim()->toString(),
            'action' => $request->string('action')->trim()->toString(),
            'model_type' => $request->string('model_type')->trim()->toString(),
            'user_id' => $request->integer('user_id'),
            'date_from' => $request->string('date_from')->trim()->toString(),
            'date_to' => $request->string('date_to')->trim()->toString(),
        ];

        $query = AuditLog::query()
            ->with('user:id,name,email')
            ->orderByDesc('created_at');

        // Apply filters
        if ($filters['search']) {
            $query->where(function ($q) use ($filters) {
                $q->where('description', 'like', "%{$filters['search']}%")
                    ->orWhereHas('user', function ($uq) use ($filters) {
                        $uq->where('name', 'like', "%{$filters['search']}%");
                    });
            });
        }

        if ($filters['action']) {
            $query->where('action', $filters['action']);
        }

        if ($filters['model_type']) {
            $query->where('model_type', $filters['model_type']);
        }

        if ($filters['user_id']) {
            $query->where('user_id', $filters['user_id']);
        }

        if ($filters['date_from']) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if ($filters['date_to']) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        $logs = $query
            ->paginate(50)
            ->withQueryString()
            ->through(fn (AuditLog $log) => $this->presenter->present($log));

        // Get filter options
        $actions = AuditLog::query()
            ->distinct()
            ->pluck('action')
            ->sort()
            ->values();

        $modelTypes = AuditLog::query()
            ->distinct()
            ->pluck('model_type')
            ->sort()
            ->values();

        $alerts = $this->detectRiskAlerts();

        return Inertia::render('inventory/AuditLog', [
            'logs' => $logs,
            'filters' => $filters,
            'filterOptions' => [
                'actions' => $actions,
                'modelTypes' => $modelTypes,
            ],
            'alerts' => $alerts,
        ]);
    }

    /**
     * @return array<int, array{title: string, description: string, severity: string}>
     */
    private function detectRiskAlerts(): array
    {
        $alerts = [];
        $oneHourAgo = Carbon::now()->subHour();

        // Mass deletion alert (5+ deletes in 1 hour)
        $massDeletions = AuditLog::query()
            ->where('action', 'delete')
            ->where('created_at', '>=', $oneHourAgo)
            ->whereNotNull('user_id')
            ->select('user_id')
            ->selectRaw('COUNT(*) as aggregate_count')
            ->groupBy('user_id')
            ->having('aggregate_count', '>=', 5)
            ->first();

        if ($massDeletions) {
            $alerts[] = [
                'title' => 'Mass Deletion Detected',
                'description' => 'A user has deleted 5+ items within the last hour.',
                'severity' => 'high',
            ];
        }

        // After-hours deletions (10pm - 6am)
        $afterHours = AuditLog::query()
            ->where('action', 'delete')
            ->where('created_at', '>=', Carbon::now()->subDay())
            ->get(['created_at'])
            ->contains(fn (AuditLog $log) => $log->created_at !== null
                && ($log->created_at->hour >= 22 || $log->created_at->hour < 6));

        if ($afterHours) {
            $alerts[] = [
                'title' => 'After-Hours Deletion Activity',
                'description' => 'Deletions detected between 10 PM and 6 AM in the last 24 hours.',
                'severity' => 'medium',
            ];
        }

        // Delete-then-restore pattern (potential data integrity issue)
        $deleteRestorePairs = AuditLog::query()
            ->whereIn('action', ['delete', 'restore'])
            ->where('created_at', '>=', Carbon::now()->subDay())
            ->whereNotNull('user_id')
            ->orderBy('created_at')
            ->get(['user_id', 'model_type', 'model_id', 'action', 'created_at'])
            ->groupBy(fn (AuditLog $log) => "{$log->user_id}|{$log->model_type}|{$log->model_id}")
            ->contains(function ($entries) {
                $lastDeleteAt = null;

                foreach ($entries as $entry) {
                    if ($entry->action === 'delete') {
                        $lastDeleteAt = $entry->created_at;

                        continue;
                    }

                    if (
                        $entry->action === 'restore'
                        && $lastDeleteAt !== null
                        && $entry->created_at !== null
                        && $entry->created_at->diffInMinutes($lastDeleteAt) <= 30
                    ) {
                        return true;
                    }
                }

                return false;
            });

        if ($deleteRestorePairs) {
            $alerts[] = [
                'title' => 'Rapid Delete-Restore Pattern',
                'description' => 'Items deleted then restored within 30 minutes. Check for accidental deletions.',
                'severity' => 'low',
            ];
        }

        return $alerts;
    }

    public function export(Request $request): StreamedResponse
    {
        $this->authorize('viewAny', AuditLog::class);

        $format = $request->string('format')->trim()->toString();

        $query = AuditLog::query()
            ->with('user:id,name,email')
            ->orderByDesc('created_at');

        // Apply same filters as index
        if ($request->string('search')->trim()->toString()) {
            $search = $request->string('search')->trim()->toString();
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($uq) use ($search) {
                        $uq->where('name', 'like', "%{$search}%");
                    });
            });
        }

        if ($request->string('action')->trim()->toString()) {
            $query->where('action', $request->string('action')->trim()->toString());
        }

        if ($request->string('model_type')->trim()->toString()) {
            $query->where('model_type', $request->string('model_type')->trim()->toString());
        }

        if ($request->integer('user_id')) {
            $query->where('user_id', $request->integer('user_id'));
        }

        if ($request->string('date_from')->trim()->toString()) {
            $query->whereDate('created_at', '>=', $request->string('date_from')->trim()->toString());
        }

        if ($request->string('date_to')->trim()->toString()) {
            $query->whereDate('created_at', '<=', $request->string('date_to')->trim()->toString());
        }

        $logs = $query->get();

        $filename = 'audit-logs-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () use ($logs) {
            $output = fopen('php://output', 'w');

            // Header
            fputcsv($output, [
                'Date', 'User', 'Email', 'Action', 'Model Type', 'Model ID',
                'Description', 'IP Address', 'User Agent',
            ]);

            foreach ($logs as $log) {
                fputcsv($output, [
                    $log->created_at->format('Y-m-d H:i:s'),
                    $log->user?->name ?? 'System',
                    $log->user?->email ?? '—',
                    $log->action,
                    $log->model_type,
                    $log->model_id ?? '—',
                    $log->description,
                    $log->ip_address ?? '—',
                    $log->user_agent ?? '—',
                ]);
            }

            fclose($output);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }
}

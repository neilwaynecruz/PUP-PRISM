<?php

declare(strict_types=1);

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\AuditLogIndexRequest;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\AuditLog\AuditLogPresenter;
use App\Services\AuditLogService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AuditLogController extends Controller
{
    public function __construct(
        private readonly AuditLogPresenter $presenter,
    ) {}

    public function index(AuditLogIndexRequest $request): Response
    {
        $filters = $request->filters();

        $logs = $this->filteredQuery($filters)
            ->with('user:id,name,email')
            ->paginate(50)
            ->withQueryString()
            ->through(fn (AuditLog $log) => $this->presenter->present($log));

        $actions = AuditLog::query()
            ->distinct()
            ->pluck('action')
            ->map(fn (string $action): array => [
                'value' => $action,
                'label' => AuditLogPresenter::actionLabel($action),
            ])
            ->sortBy('label')
            ->values();

        $modelTypes = AuditLog::query()
            ->distinct()
            ->pluck('model_type')
            ->map(fn (string $modelType): array => [
                'value' => $modelType,
                'label' => AuditLogPresenter::modelLabel($modelType),
            ])
            ->sortBy('label')
            ->values();

        $actors = User::query()
            ->whereIn('id', AuditLog::query()->whereNotNull('user_id')->select('user_id'))
            ->orderBy('name')
            ->get(['id', 'name', 'email'])
            ->map(fn (User $user): array => [
                'id' => (int) $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ])
            ->values();

        return Inertia::render('inventory/AuditLog', [
            'logs' => $logs,
            'filters' => $filters,
            'filterOptions' => [
                'actions' => $actions,
                'modelTypes' => $modelTypes,
                'users' => $actors,
            ],
            'alerts' => $this->detectRiskAlerts(),
        ]);
    }

    /**
     * @param  array{search: string, action: string, model_type: string, user_id: int, date_from: string, date_to: string, sort: string}  $filters
     * @return Builder<AuditLog>
     */
    private function filteredQuery(array $filters): Builder
    {
        $direction = $filters['sort'] === 'oldest' ? 'asc' : 'desc';

        return AuditLog::query()
            ->when($filters['search'] !== '', function (Builder $query) use ($filters): void {
                $search = $filters['search'];

                $query->where(function (Builder $nested) use ($search): void {
                    $nested->where('description', 'like', "%{$search}%")
                        ->orWhere('action', 'like', "%{$search}%")
                        ->orWhere('ip_address', 'like', "%{$search}%")
                        ->orWhereHas('user', function (Builder $userQuery) use ($search): void {
                            $userQuery->where('name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                        });

                    if (ctype_digit($search)) {
                        $nested->orWhere('model_id', (int) $search);
                    }
                });
            })
            ->when($filters['action'] !== '', fn (Builder $query) => $query->where('action', $filters['action']))
            ->when($filters['model_type'] !== '', fn (Builder $query) => $query->where('model_type', $filters['model_type']))
            ->when($filters['user_id'] > 0, fn (Builder $query) => $query->where('user_id', $filters['user_id']))
            ->when($filters['date_from'] !== '', fn (Builder $query) => $query->whereDate('created_at', '>=', $filters['date_from']))
            ->when($filters['date_to'] !== '', fn (Builder $query) => $query->whereDate('created_at', '<=', $filters['date_to']))
            ->orderBy('created_at', $direction)
            ->orderBy('id', $direction);
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
            ->havingRaw('COUNT(*) >= ?', [5])
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

    public function export(AuditLogIndexRequest $request): StreamedResponse
    {
        $filters = $request->filters();
        $query = $this->filteredQuery($filters)->with('user:id,name,email');

        AuditLogService::logCustom(
            'audit_export',
            __('Audit log exported to CSV.'),
            null,
            null,
            ['filters' => array_filter($filters, fn (mixed $value): bool => $value !== '' && $value !== 0)],
        );

        $filename = 'audit-logs-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () use ($query): void {
            $output = fopen('php://output', 'w');

            fputcsv($output, [
                'Date', 'User', 'Email', 'Action', 'Action Code', 'Record Type', 'Record ID',
                'Description', 'Changes', 'IP Address',
            ]);

            foreach ($query->lazy(500) as $log) {
                fputcsv($output, array_map($this->csvSafe(...), [
                    $log->created_at?->format('Y-m-d H:i:s') ?? '',
                    $log->user?->name ?? 'System',
                    $log->user?->email ?? '',
                    AuditLogPresenter::actionLabel($log->action),
                    $log->action,
                    AuditLogPresenter::modelLabel($log->model_type),
                    $log->model_id ?? '',
                    $log->description,
                    $this->presenter->changeSummary($log),
                    $log->ip_address ?? '',
                ]));
            }

            fclose($output);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store, private',
        ]);
    }

    /**
     * Neutralise spreadsheet formulas so exported audit text cannot execute when opened.
     */
    private function csvSafe(mixed $value): string
    {
        $value = (string) $value;

        if ($value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'".$value;
        }

        return $value;
    }
}

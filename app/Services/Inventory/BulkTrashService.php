<?php

namespace App\Services\Inventory;

use App\Models\User;
use App\Services\AuditLogService;
use App\Services\DashboardStatsCache;
use Closure;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class BulkTrashService
{
    public function __construct(
        private readonly DashboardStatsCache $dashboardStatsCache,
    ) {}

    /**
     * Restore soft-deleted records inside a locked transaction with policy enforcement, batch auditing, and cache invalidation.
     *
     * @template TModel of Model
     *
     * @param  class-string<TModel>  $modelClass
     * @param  list<int>  $ids
     * @param  Closure(TModel): string  $labelResolver
     * @param  (Closure(EloquentCollection<int, TModel>): void)|null  $afterRestore
     */
    public function bulkRestore(
        string $modelClass,
        array $ids,
        User $actor,
        Closure $labelResolver,
        ?Closure $afterRestore = null,
    ): int {
        if ($ids === []) {
            return 0;
        }

        $deletedAtColumn = $modelClass::make()->qualifyColumn('deleted_at');

        /** @var EloquentCollection<int, TModel> $records */
        $records = $modelClass::query()
            ->withoutGlobalScope(SoftDeletingScope::class)
            ->whereNotNull($deletedAtColumn)
            ->whereIn('id', $ids)
            ->orderBy('id')
            ->get();

        foreach ($records as $record) {
            Gate::forUser($actor)->authorize('restore', $record);
        }

        $restoredCount = DB::transaction(function () use ($modelClass, $records, $labelResolver, $deletedAtColumn): int {
            if ($records->isEmpty()) {
                return 0;
            }

            $recordIds = $records->modelKeys();

            /** @var EloquentCollection<int, TModel> $lockedRecords */
            $lockedRecords = $modelClass::query()
                ->withoutGlobalScope(SoftDeletingScope::class)
                ->whereNotNull($deletedAtColumn)
                ->whereIn('id', $recordIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($lockedRecords->isEmpty()) {
                return 0;
            }

            $lockedIds = $lockedRecords->modelKeys();
            $now = now()->toImmutable();
            $entityName = class_basename($modelClass);

            $modelClass::query()
                ->withoutGlobalScope(SoftDeletingScope::class)
                ->whereNotNull($deletedAtColumn)
                ->whereIn('id', $lockedIds)
                ->update([
                    'deleted_at' => null,
                    'updated_at' => $now,
                ]);

            $auditEntries = [];

            foreach ($lockedRecords as $record) {
                $record->setAttribute('deleted_at', null);
                $label = trim($labelResolver($record));

                $auditEntries[] = [
                    'action' => 'restore',
                    'description' => trim("{$entityName} {$label} restored."),
                    'model' => $record,
                    'old_values' => null,
                    'new_values' => $record->toArray(),
                ];
            }

            AuditLogService::logBatch($auditEntries);

            return count($lockedIds);
        });

        if ($restoredCount > 0) {
            $this->dashboardStatsCache->invalidate();

            if ($afterRestore !== null) {
                $afterRestore($records);
            }
        }

        return $restoredCount;
    }

    /**
     * Permanently delete eligible soft-deleted records with policy checks, historical safeguards, and cache invalidation.
     *
     * @template TModel of Model
     *
     * @param  class-string<TModel>  $modelClass
     * @param  list<int>  $ids
     * @param  (Closure(TModel): bool)|null  $canForceDelete
     * @param  (Closure(int): void)|null  $afterForceDelete
     * @return array{deleted: int, skipped: int}
     */
    public function bulkForceDelete(
        string $modelClass,
        array $ids,
        User $actor,
        ?Closure $canForceDelete = null,
        ?Closure $afterForceDelete = null,
    ): array {
        if ($ids === []) {
            return ['deleted' => 0, 'skipped' => 0];
        }

        $deletedAtColumn = $modelClass::make()->qualifyColumn('deleted_at');

        /** @var EloquentCollection<int, TModel> $records */
        $records = $modelClass::query()
            ->withoutGlobalScope(SoftDeletingScope::class)
            ->whereNotNull($deletedAtColumn)
            ->whereIn('id', $ids)
            ->orderBy('id')
            ->get();

        foreach ($records as $record) {
            Gate::forUser($actor)->authorize('forceDelete', $record);
        }

        $deleted = 0;
        $skipped = 0;

        foreach ($records as $record) {
            if ($canForceDelete !== null && ! $canForceDelete($record)) {
                $skipped++;

                continue;
            }

            try {
                DB::transaction(function () use ($record): void {
                    AuditLogService::logForceDeleted($record);
                    $record->forceDelete();
                });

                $deleted++;
            } catch (QueryException $e) {
                report($e);
                $skipped++;
            }
        }

        if ($deleted > 0) {
            $this->dashboardStatsCache->invalidate();

            if ($afterForceDelete !== null) {
                $afterForceDelete($deleted);
            }
        }

        return [
            'deleted' => $deleted,
            'skipped' => $skipped,
        ];
    }
}

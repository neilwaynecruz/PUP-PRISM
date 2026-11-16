<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AuditLog;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class AuditLogService
{
    public static function log(
        string $action,
        string $description,
        ?Model $model = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?int $userId = null,
    ): void {
        AuditLog::create([
            'user_id' => $userId ?? Auth::id(),
            'action' => $action,
            'model_type' => $model ? class_basename($model) : 'System',
            'model_id' => $model?->getKey(),
            'description' => $description,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
        ]);
    }

    public static function logCreated(Model $model, ?string $description = null): void
    {
        self::log(
            'create',
            $description ?? self::defaultDescription('created', $model),
            $model,
            null,
            $model->toArray(),
        );
    }

    public static function logUpdated(Model $model, array $oldValues, ?string $description = null): void
    {
        self::log(
            'update',
            $description ?? self::defaultDescription('updated', $model),
            $model,
            $oldValues,
            $model->toArray(),
        );
    }

    public static function logDeleted(Model $model, ?string $description = null): void
    {
        self::log(
            'delete',
            $description ?? self::defaultDescription('deleted', $model),
            $model,
            $model->toArray(),
            null,
        );
    }

    public static function logRestored(Model $model, ?string $description = null): void
    {
        self::log(
            'restore',
            $description ?? self::defaultDescription('restored', $model),
            $model,
            null,
            $model->toArray(),
        );
    }

    public static function logForceDeleted(Model $model, ?string $description = null): void
    {
        self::log(
            'force_delete',
            $description ?? self::defaultDescription('permanently deleted', $model),
            $model,
            $model->toArray(),
            null,
        );
    }

    /**
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     */
    public static function logCustom(
        string $action,
        string $description,
        ?Model $model = null,
        ?array $oldValues = null,
        ?array $newValues = null,
    ): void {
        self::log($action, $description, $model, $oldValues, $newValues);
    }

    /**
     * Insert multiple audit log records in chunked batch statements.
     *
     * @param  array<int, array{
     *     action: string,
     *     description: string,
     *     model?: Model|null,
     *     old_values?: array<string, mixed>|null,
     *     new_values?: array<string, mixed>|null,
     *     user_id?: int|null,
     * }>  $entries
     */
    public static function logBatch(array $entries): void
    {
        if ($entries === []) {
            return;
        }

        $defaultUserId = Auth::id();
        $ipAddress = Request::ip();
        $userAgent = Request::userAgent();
        $now = CarbonImmutable::now()->toDateTimeString();
        $rows = [];

        foreach ($entries as $entry) {
            $model = $entry['model'] ?? null;
            $oldValues = $entry['old_values'] ?? null;
            $newValues = $entry['new_values'] ?? null;

            $rows[] = [
                'user_id' => $entry['user_id'] ?? $defaultUserId,
                'action' => $entry['action'],
                'model_type' => $model ? class_basename($model) : 'System',
                'model_id' => $model?->getKey(),
                'description' => $entry['description'],
                'old_values' => $oldValues !== null ? json_encode($oldValues, JSON_THROW_ON_ERROR) : null,
                'new_values' => $newValues !== null ? json_encode($newValues, JSON_THROW_ON_ERROR) : null,
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($rows, 100) as $chunk) {
            AuditLog::query()->insert($chunk);
        }
    }

    private static function defaultDescription(string $action, Model $model): string
    {
        $name = '';

        if (method_exists($model, 'getNameAttribute') || $model->getAttribute('name')) {
            $name = $model->getAttribute('name') ?? '';
        } elseif ($model->getAttribute('sku')) {
            $name = $model->getAttribute('sku');
        } elseif ($model->getKey() !== null) {
            $name = '#'.$model->getKey();
        }

        return class_basename($model).($name ? " {$name}" : '')." {$action}.";
    }
}

<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AuditLog;
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

    public static function logCustom(string $action, string $description, ?Model $model = null): void
    {
        self::log($action, $description, $model);
    }

    private static function defaultDescription(string $action, Model $model): string
    {
        $name = '';

        if (method_exists($model, 'getNameAttribute') || $model->name ?? false) {
            $name = $model->name ?? '';
        } elseif ($model->sku ?? false) {
            $name = $model->sku;
        } elseif ($model->id) {
            $name = '#'.$model->getKey();
        }

        return class_basename($model).($name ? " {$name}" : '')." {$action}.";
    }
}

<?php

namespace App\Services\AuditLog;

use App\Models\AuditLog;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class AuditLogPresenter
{
    /**
     * @var array<int, string>
     */
    private const HIDDEN_FIELDS = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'verification_token_hash',
        'token',
        'plain_text_token',
        'secret',
        'api_token',
        'signature',
    ];

    /**
     * Readable labels for the action codes written by the application.
     *
     * @var array<string, string>
     */
    private const ACTION_LABELS = [
        'create' => 'Created',
        'update' => 'Updated',
        'delete' => 'Moved to trash',
        'restore' => 'Restored',
        'force_delete' => 'Permanently deleted',
        'approve' => 'Approved',
        'reject' => 'Rejected',
        'issue' => 'Issued',
        'receive' => 'Stock received',
        'receive_batch' => 'Batch stock received',
        'transfer' => 'Transferred',
        'cancel' => 'Cancelled',
        'submit' => 'Submitted for review',
        'review' => 'Reviewed',
        'complete' => 'Completed',
        'deactivate' => 'Deactivated',
        'reactivate' => 'Reactivated',
        'revoke_tokens' => 'API tokens revoked',
        'revoke_sessions' => 'Sessions revoked',
        'two_factor_enabled' => 'Two-factor authentication enabled',
        'two_factor_disabled' => 'Two-factor authentication disabled',
        'handover_initiate' => 'Handover initiated',
        'handover_verify' => 'Handover verified',
        'handover_cancel' => 'Handover cancelled',
        'handover_resend' => 'Handover verification resent',
        'alert_acknowledge' => 'Alert acknowledged',
        'alert_assign' => 'Alert assigned',
        'alert_resolve' => 'Alert resolved',
        'api_token_create' => 'API token created',
        'api_token_revoke' => 'API token revoked',
        'notification_preferences_update' => 'Notification preferences updated',
        'password_change' => 'Password changed',
        'profile_update' => 'Profile updated',
        'profile_email_change' => 'Profile email changed',
        'profile_delete' => 'Account deleted',
        'stock_adjustment' => 'Stock adjusted',
        'cycle_count' => 'Cycle count recorded',
        'audit_export' => 'Audit log exported',
    ];

    /**
     * Readable labels for stored record types.
     *
     * @var array<string, string>
     */
    private const MODEL_LABELS = [
        'HandoverLog' => 'Handover',
        'InventoryAlert' => 'Inventory alert',
        'InventoryCountSession' => 'Physical count',
        'InventoryCountLine' => 'Physical count line',
        'AssetLifecycleEvent' => 'Asset lifecycle change',
        'AssetTransfer' => 'Asset transfer',
        'AssetMaintenance' => 'Asset maintenance',
        'PurchaseOrder' => 'Purchase order',
        'RequisitionTemplate' => 'Requisition template',
        'System' => 'System',
    ];

    public static function actionLabel(string $action): string
    {
        return self::ACTION_LABELS[$action] ?? Str::ucfirst(Str::lower(Str::headline($action)));
    }

    public static function modelLabel(string $modelType): string
    {
        return self::MODEL_LABELS[$modelType] ?? Str::headline($modelType);
    }

    /**
     * Outcome category used to colour-code an action consistently.
     */
    public static function actionTone(string $action): string
    {
        return match (true) {
            in_array($action, ['delete', 'force_delete', 'reject', 'deactivate', 'revoke_tokens', 'revoke_sessions', 'handover_cancel', 'profile_delete', 'two_factor_disabled'], true) => 'danger',
            in_array($action, ['create', 'approve', 'complete', 'handover_verify', 'alert_resolve', 'reactivate', 'restore', 'two_factor_enabled'], true) => 'success',
            in_array($action, ['update', 'review', 'submit', 'alert_acknowledge', 'alert_assign', 'handover_resend', 'profile_update', 'profile_email_change', 'notification_preferences_update'], true) => 'info',
            in_array($action, ['issue', 'receive', 'receive_batch', 'transfer', 'stock_adjustment', 'cycle_count', 'handover_initiate'], true) => 'movement',
            default => 'neutral',
        };
    }

    /**
     * @var array<int, string>
     */
    private const NOISE_FIELDS = [
        'updated_at',
    ];

    /**
     * @return array<string, mixed>
     */
    public function present(AuditLog $log): array
    {
        $oldValues = $this->sanitizeValues($log->old_values);
        $newValues = $this->sanitizeValues($log->new_values);

        return [
            'id' => (int) $log->id,
            'action' => $log->action,
            'action_label' => self::actionLabel($log->action),
            'action_tone' => self::actionTone($log->action),
            'model_type' => $log->model_type,
            'model_label' => self::modelLabel($log->model_type),
            'model_id' => $log->model_id,
            'description' => $log->description,
            'user' => $log->relationLoaded('user') && $log->user
                ? [
                    'id' => (int) $log->user->id,
                    'name' => $log->user->name,
                    'email' => $log->user->email,
                ]
                : null,
            'created_at' => $log->created_at?->toIso8601String(),
            'ip_address' => $log->ip_address,
            'changes' => $this->buildChanges($oldValues, $newValues),
        ];
    }

    /**
     * Plain-text change summary for exports, using the same redaction rules as the page.
     */
    public function changeSummary(AuditLog $log): string
    {
        $changes = $this->buildChanges(
            $this->sanitizeValues($log->old_values),
            $this->sanitizeValues($log->new_values),
        );

        return collect($changes)
            ->map(fn (array $change): string => "{$change['label']}: {$change['old_value']} -> {$change['new_value']}")
            ->implode('; ');
    }

    /**
     * @param  array<string, mixed>  $oldValues
     * @param  array<string, mixed>  $newValues
     * @return array<int, array{field: string, label: string, old_value: string, new_value: string}>
     */
    private function buildChanges(array $oldValues, array $newValues): array
    {
        $flattenedOld = Arr::dot($oldValues);
        $flattenedNew = Arr::dot($newValues);
        $fields = array_values(array_unique([
            ...array_keys($flattenedOld),
            ...array_keys($flattenedNew),
        ]));

        $changes = [];

        foreach ($fields as $field) {
            if ($this->shouldHideField($field) || $this->isNoiseField($field)) {
                continue;
            }

            $oldValue = $flattenedOld[$field] ?? null;
            $newValue = $flattenedNew[$field] ?? null;

            if ($oldValue === $newValue) {
                continue;
            }

            $changes[] = [
                'field' => $field,
                'label' => $this->humanizeField($field),
                'old_value' => $this->formatValue($field, $oldValue),
                'new_value' => $this->formatValue($field, $newValue),
            ];
        }

        return $changes;
    }

    /**
     * @param  array<string, mixed>|null  $values
     * @return array<string, mixed>
     */
    private function sanitizeValues(?array $values): array
    {
        if (! is_array($values)) {
            return [];
        }

        $sanitized = [];

        foreach ($values as $key => $value) {
            if ($this->shouldHideField((string) $key)) {
                continue;
            }

            if (is_array($value)) {
                $nested = $this->sanitizeValues($value);

                if ($nested === []) {
                    continue;
                }

                $sanitized[$key] = $nested;

                continue;
            }

            $sanitized[$key] = $value;
        }

        return $sanitized;
    }

    private function shouldHideField(string $field): bool
    {
        $normalized = Str::lower($field);

        foreach (self::HIDDEN_FIELDS as $hiddenField) {
            if (Str::contains($normalized, $hiddenField)) {
                return true;
            }
        }

        return false;
    }

    private function isNoiseField(string $field): bool
    {
        return in_array(Str::afterLast($field, '.'), self::NOISE_FIELDS, true);
    }

    private function humanizeField(string $field): string
    {
        $field = Str::afterLast($field, '.');

        if (Str::endsWith($field, '_id')) {
            $field = Str::replaceLast('_id', '', $field);
        }

        return Str::headline($field);
    }

    private function formatValue(string $field, mixed $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        if (is_array($value)) {
            if (isset($value['name']) && is_string($value['name'])) {
                return $value['name'];
            }

            if (isset($value['title']) && is_string($value['title'])) {
                return $value['title'];
            }

            return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '—';
        }

        $stringValue = trim((string) $value);

        if ($this->looksLikeDateField($field, $stringValue)) {
            return CarbonImmutable::parse($stringValue)->format('M j, Y g:i A');
        }

        if ($field === 'is_active') {
            return in_array(Str::lower($stringValue), ['1', 'true', 'yes'], true) ? 'Yes' : 'No';
        }

        return $stringValue;
    }

    private function looksLikeDateField(string $field, string $value): bool
    {
        if (! Str::contains($field, ['_at', '_date'])) {
            return false;
        }

        try {
            CarbonImmutable::parse($value);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}

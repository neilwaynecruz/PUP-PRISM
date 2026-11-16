<?php

namespace App\Models;

use App\Enums\MaintenanceStatus;
use App\Enums\MaintenanceType;
use Database\Factories\AssetMaintenanceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

#[Fillable([
    'asset_id', 'maintenance_type', 'status', 'reported_issue', 'description',
    'reported_by', 'reported_date', 'assigned_to', 'service_provider',
    'scheduled_for', 'completed_at', 'repair_cost', 'notes', 'findings',
])]
class AssetMaintenance extends Model
{
    /** @use HasFactory<AssetMaintenanceFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'maintenance_type' => MaintenanceType::class,
            'status' => MaintenanceStatus::class,
            'reported_date' => 'date',
            'scheduled_for' => 'date',
            'completed_at' => 'datetime',
            'repair_cost' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<Asset, $this> */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    /** @return BelongsTo<User, $this> */
    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    /** @return BelongsTo<User, $this> */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /** @return MorphMany<Attachment, $this> */
    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }
}

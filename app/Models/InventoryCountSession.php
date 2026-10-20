<?php

namespace App\Models;

use App\Enums\InventoryCountStatus;
use Database\Factories\InventoryCountSessionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

#[Fillable([
    'reference_no', 'location_id', 'created_by', 'assigned_to', 'approved_by',
    'counting_date', 'scope', 'status', 'snapshot_at', 'movement_cutoff_id', 'submitted_at',
    'approved_at', 'completed_at', 'cancelled_at', 'remarks',
])]
class InventoryCountSession extends Model
{
    /** @use HasFactory<InventoryCountSessionFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'counting_date' => 'date',
            'status' => InventoryCountStatus::class,
            'snapshot_at' => 'datetime',
            'movement_cutoff_id' => 'integer',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(InventoryCountLine::class);
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }
}

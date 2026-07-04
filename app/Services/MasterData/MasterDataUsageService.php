<?php

namespace App\Services\MasterData;

use App\Models\Booking;
use App\Models\Category;
use App\Models\Department;
use App\Models\HandoverLog;
use App\Models\Origin;
use App\Models\Position;
use App\Models\Requisition;
use App\Models\StockMovement;

class MasterDataUsageService
{
    /**
     * @return array<string, int>
     */
    public function departmentCounts(Department $department): array
    {
        return [
            'positions' => $department->positions()->count(),
            'active_positions' => $department->positions()->where('is_active', true)->count(),
        ];
    }

    /**
     * @return array<int, string>
     */
    public function departmentWarnings(Department $department): array
    {
        $counts = $this->departmentCounts($department);
        $warnings = [];

        if ($counts['positions'] > 0) {
            $warnings[] = __(':count position(s) belong to this department.', ['count' => $counts['positions']]);
        }

        if ($counts['active_positions'] > 0 && ! $department->is_active) {
            $warnings[] = __('Reactivating this department affects :count active position(s).', ['count' => $counts['active_positions']]);
        }

        return $warnings;
    }

    public function canDeleteDepartment(Department $department): bool
    {
        return $department->positions()->count() === 0;
    }

    /**
     * @return array<string, int>
     */
    public function positionCounts(Position $position): array
    {
        return [
            'users' => $position->users()->count(),
            'assets' => $position->assets()->count(),
            'bookings' => Booking::query()
                ->where(function ($query) use ($position): void {
                    $query
                        ->where('requester_position_id', $position->id)
                        ->orWhere('approver_position_id', $position->id);
                })
                ->count(),
            'requisitions' => Requisition::query()
                ->where(function ($query) use ($position): void {
                    $query
                        ->where('requester_position_id', $position->id)
                        ->orWhere('approver_position_id', $position->id)
                        ->orWhere('issued_position_id', $position->id);
                })
                ->count(),
            'handovers' => HandoverLog::query()
                ->where(function ($query) use ($position): void {
                    $query
                        ->where('from_position_id', $position->id)
                        ->orWhere('to_position_id', $position->id);
                })
                ->count(),
            'stock_movements' => StockMovement::query()
                ->where('accountable_position_id', $position->id)
                ->count(),
        ];
    }

    /**
     * @return array<int, string>
     */
    public function positionWarnings(Position $position): array
    {
        $counts = $this->positionCounts($position);
        $warnings = [];

        if ($counts['users'] > 0) {
            $warnings[] = __(':count user(s) are assigned to this position.', ['count' => $counts['users']]);
        }

        if ($counts['assets'] > 0) {
            $warnings[] = __(':count asset(s) are accountable to this position.', ['count' => $counts['assets']]);
        }

        if ($counts['bookings'] > 0) {
            $warnings[] = __(':count booking(s) reference this position.', ['count' => $counts['bookings']]);
        }

        if ($counts['requisitions'] > 0) {
            $warnings[] = __(':count requisition(s) reference this position.', ['count' => $counts['requisitions']]);
        }

        if ($counts['handovers'] > 0) {
            $warnings[] = __(':count handover record(s) reference this position.', ['count' => $counts['handovers']]);
        }

        if ($counts['stock_movements'] > 0) {
            $warnings[] = __(':count stock movement(s) reference this position.', ['count' => $counts['stock_movements']]);
        }

        return $warnings;
    }

    public function canDeletePosition(Position $position): bool
    {
        $counts = $this->positionCounts($position);

        return $counts['users'] === 0 && $counts['assets'] === 0;
    }

    public function canDeactivatePosition(Position $position): bool
    {
        return $position->users()->where('is_active', true)->count() === 0;
    }

    /**
     * @return array<string, int>
     */
    public function referenceCounts(Category|Origin $reference): array
    {
        return [
            'products' => $reference->products()->count(),
        ];
    }

    /**
     * @return array<int, string>
     */
    public function referenceWarnings(Category|Origin $reference): array
    {
        $products = $reference->products()->count();

        if ($products === 0) {
            return [];
        }

        return [
            __(':count product(s) use this reference value.', ['count' => $products]),
        ];
    }

    public function canDeleteReference(Category|Origin $reference): bool
    {
        return $reference->products()->count() === 0;
    }

    /**
     * @param  array<string, int>  $counts
     * @return array<string, int>
     */
    public function presentCounts(array $counts): array
    {
        return array_filter($counts, fn (int $count): bool => $count > 0);
    }
}

<?php

namespace Database\Factories;

use App\Enums\InventoryCountReviewStatus;
use App\Models\Asset;
use App\Models\InventoryCountLine;
use App\Models\InventoryCountSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryCountLine>
 */
class InventoryCountLineFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'inventory_count_session_id' => InventoryCountSession::factory(),
            'product_id' => fn (array $attributes): int => Asset::query()->findOrFail($attributes['asset_id'])->product_id,
            'stock_lot_id' => null,
            'asset_id' => Asset::factory(),
            'expected_qty_snapshot' => 1,
            'counted_qty' => null,
            'review_status' => InventoryCountReviewStatus::Pending,
        ];
    }
}

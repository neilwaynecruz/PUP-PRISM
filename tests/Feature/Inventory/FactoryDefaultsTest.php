<?php

use App\Models\Asset;
use App\Models\Position;
use App\Models\StockLot;
use App\Models\User;

test('assignment factories preserve supplied positions and create missing positions', function () {
    $position = Position::factory()->create();

    expect(Asset::factory()->assignedToPosition($position)->create()->position_id)->toBe($position->id)
        ->and(User::factory()->assignedPosition($position)->create()->position_id)->toBe($position->id);

    $asset = Asset::factory()->assignedToPosition()->create();
    $user = User::factory()->assignedPosition()->create();

    expect(Position::query()->find($asset->position_id))->toBeInstanceOf(Position::class)
        ->and(Position::query()->find($user->position_id))->toBeInstanceOf(Position::class);
});

test('stock lot factory supports both expiring and non expiring inventory', function () {
    fake()->seed(20261116);

    try {
        $expiring = 0;
        $nonExpiring = 0;

        for ($index = 0; $index < 40; $index++) {
            $attributes = StockLot::factory()->definition();

            expect($attributes['qty_remaining'])->toBeGreaterThanOrEqual(0)
                ->toBeLessThanOrEqual($attributes['qty_received']);

            if ($attributes['expires_at'] === null) {
                $nonExpiring++;
            } else {
                $expiring++;
                expect($attributes['expires_at'])->toMatch('/^\d{4}-\d{2}-\d{2}$/');
            }
        }

        expect($expiring)->toBeGreaterThan(0)
            ->and($nonExpiring)->toBeGreaterThan(0);
    } finally {
        fake()->seed();
    }
});

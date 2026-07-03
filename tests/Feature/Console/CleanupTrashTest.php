<?php

use App\Models\Booking;
use App\Models\Product;
use App\Models\Requisition;
use App\Support\SchedulerHeartbeat;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::findOrCreate('Admin');
});

test('trash cleanup permanently deletes old soft-deleted records', function () {
    $cutoff = Carbon::now()->subDays(31);

    $product = Product::factory()->create();
    $product->delete();
    Product::withTrashed()->whereKey($product->id)->update(['deleted_at' => $cutoff]);

    $booking = Booking::factory()->create();
    $booking->delete();
    Booking::withTrashed()->whereKey($booking->id)->update(['deleted_at' => $cutoff]);

    $requisition = Requisition::factory()->create();
    $requisition->delete();
    Requisition::withTrashed()->whereKey($requisition->id)->update(['deleted_at' => $cutoff]);

    $this->artisan('trash:cleanup', ['--days' => 30])
        ->assertExitCode(0);

    expect(Product::withTrashed()->find($product->id))->toBeNull();
    expect(Booking::withTrashed()->find($booking->id))->toBeNull();
    expect(Requisition::withTrashed()->find($requisition->id))->toBeNull();
});

test('trash cleanup dry run does not delete records', function () {
    $cutoff = Carbon::now()->subDays(31);

    $product = Product::factory()->create();
    $product->delete();
    Product::withTrashed()->whereKey($product->id)->update(['deleted_at' => $cutoff]);

    $this->artisan('trash:cleanup', ['--days' => 30, '--dry-run' => true])
        ->assertExitCode(0);

    expect(Product::withTrashed()->find($product->id))->not->toBeNull();
});

test('trash cleanup records scheduler heartbeat when items are deleted', function () {
    Cache::flush();

    $cutoff = Carbon::now()->subDays(31);

    $product = Product::factory()->create();
    $product->delete();
    Product::withTrashed()->whereKey($product->id)->update(['deleted_at' => $cutoff]);

    $this->artisan('trash:cleanup', ['--days' => 30])
        ->assertExitCode(0);

    expect(Cache::get(SchedulerHeartbeat::cacheKey(SchedulerHeartbeat::COMMAND_TRASH_CLEANUP)))
        ->not->toBeNull();
});

test('trash cleanup skips recently deleted records', function () {
    $product = Product::factory()->create();
    $product->delete();

    $this->artisan('trash:cleanup', ['--days' => 30])
        ->assertExitCode(0);

    expect(Product::withTrashed()->find($product->id))->not->toBeNull();
});

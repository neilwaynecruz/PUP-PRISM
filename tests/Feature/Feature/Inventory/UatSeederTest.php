<?php

use App\Models\Asset;
use App\Models\Booking;
use App\Models\Department;
use App\Models\Position;
use App\Models\Requisition;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;

test('database seeder creates uat organization and walkthrough data', function () {
    $this->seed(DatabaseSeeder::class);

    expect(Department::query()->where('name', 'College of Engineering')->exists())->toBeTrue();
    expect(Position::query()->where('code', 'POS-COE-PC')->exists())->toBeTrue();
    expect(User::query()->where('email', 'admin@local.test')->whereNotNull('position_id')->exists())->toBeTrue();
    expect(User::query()->where('email', 'eng.custodian@local.test')->exists())->toBeTrue();
    expect(Asset::query()->where('tag_code', 'AST-COE-0001')->whereNotNull('position_id')->exists())->toBeTrue();
    expect(Booking::query()->count())->toBeGreaterThanOrEqual(3);
    expect(Requisition::query()->count())->toBeGreaterThanOrEqual(3);
});

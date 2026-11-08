<?php

use App\Models\Asset;
use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->withoutVite();
    Role::findOrCreate('Property Custodian');
    Role::findOrCreate('Requester');
});

test('reviewed identifiers find existing assets without changing inventory', function (string $search) {
    $user = User::factory()->withTwoFactor()->create();
    $user->assignRole('Property Custodian');
    $asset = Asset::factory()->create(['tag_code' => 'AST-OCR-001', 'serial_number' => 'SN-OCR-901']);

    $this->actingAs($user)->get(route('inventory.assets.index', ['search' => $search]))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page->has('assets.data', 1)->where('assets.data.0.id', $asset->id));

    expect(Asset::count())->toBe(1);
})->with(['ast-ocr-001', 'sn-ocr-901']);

test('partial identifiers return candidates and unknown identifiers return no results', function () {
    $user = User::factory()->withTwoFactor()->create();
    $user->assignRole('Property Custodian');
    Asset::factory()->create(['tag_code' => 'AST-OCR-001']);
    Asset::factory()->create(['tag_code' => 'AST-OCR-002']);

    $this->actingAs($user)->get(route('inventory.assets.index', ['search' => 'AST-OCR']))
        ->assertSuccessful()->assertInertia(fn ($page) => $page->has('assets.data', 2));
    $this->get(route('inventory.assets.index', ['search' => 'NO-SUCH-ASSET']))
        ->assertSuccessful()->assertInertia(fn ($page) => $page->has('assets.data', 0));
});

test('asset lookup and individual records remain forbidden to requesters', function () {
    $user = User::factory()->create();
    $user->assignRole('Requester');
    $asset = Asset::factory()->create();

    $this->actingAs($user)->get(route('inventory.assets.index', ['search' => $asset->tag_code]))->assertForbidden();
    $this->get(route('inventory.assets.show', $asset))->assertForbidden();
});

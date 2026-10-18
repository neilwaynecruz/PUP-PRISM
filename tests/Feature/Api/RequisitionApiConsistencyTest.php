<?php

use App\Enums\ProductType;
use App\Enums\RequisitionStatus;
use App\Models\Category;
use App\Models\Origin;
use App\Models\Product;
use App\Models\Requisition;
use App\Models\User;
use App\Notifications\RequisitionSubmittedNotification;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    (new RoleSeeder)->run();
    $this->withoutVite();
});

function apiConsistencyUser(string $role, array $attributes = []): User
{
    $factory = User::factory();
    if (in_array($role, ['Admin', 'Supply Head'], true)) {
        $factory = $factory->withTwoFactor();
    }
    $user = $factory->create($attributes);
    $user->assignRole($role);

    return $user;
}

test('api requisition creation logs audit entry and sends notification', function () {
    Notification::fake();

    $supplyHead = apiConsistencyUser('Supply Head');
    $user = apiConsistencyUser('Admin');
    $token = $user->createToken('test-token', ['write'])->plainTextToken;

    $product = Product::factory()->create([
        'type' => ProductType::Consumable,
        'origin_id' => Origin::factory()->create()->id,
        'category_id' => Category::factory()->create()->id,
    ]);

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/requisitions', [
            'notes' => 'API consistency check',
            'lines' => [
                ['product_id' => $product->id, 'qty_requested' => 10],
            ],
        ]);

    $response->assertCreated();

    $createdRequisition = Requisition::query()->latest('id')->firstOrFail();

    // Verify audit log exists
    $this->assertDatabaseHas('audit_logs', [
        'model_type' => 'Requisition',
        'model_id' => $createdRequisition->id,
        'action' => 'create',
        'user_id' => $user->id,
    ]);

    // Verify notification sent to Supply Head
    Notification::assertSentTo(
        $supplyHead,
        RequisitionSubmittedNotification::class,
    );
});

test('api requisition creation rejects duplicate product lines', function () {
    $user = apiConsistencyUser('Admin');
    $token = $user->createToken('test-token', ['write'])->plainTextToken;

    $product = Product::factory()->create([
        'type' => ProductType::Consumable,
        'origin_id' => Origin::factory()->create()->id,
        'category_id' => Category::factory()->create()->id,
    ]);

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/requisitions', [
            'lines' => [
                ['product_id' => $product->id, 'qty_requested' => 3],
                ['product_id' => $product->id, 'qty_requested' => 5], // duplicate product_id
            ],
        ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['lines.0.product_id', 'lines.1.product_id']);
});

test('web and api requisition submission produce consistent database outcomes', function () {
    $webUser = apiConsistencyUser('Supply Head');
    $apiUser = apiConsistencyUser('Supply Head');
    $token = $apiUser->createToken('api-token', ['write'])->plainTextToken;

    $product = Product::factory()->create([
        'type' => ProductType::Consumable,
        'sku' => 'CONSISTENCY-SKU-1',
        'origin_id' => Origin::factory()->create()->id,
        'category_id' => Category::factory()->create()->id,
    ]);

    // Web submission
    $csrfToken = 'csrf-web-consistent-token';
    $this->actingAs($webUser)
        ->withSession(['_token' => $csrfToken])
        ->post(route('inventory.requisitions.store', absolute: false), [
            '_token' => $csrfToken,
            'notes' => 'Submitted via Web',
            'lines' => [
                ['sku' => $product->sku, 'qty_requested' => 4],
            ],
        ])
        ->assertRedirect();

    $webRequisition = Requisition::query()->where('notes', 'Submitted via Web')->firstOrFail();
    expect($webRequisition->status)->toBe(RequisitionStatus::Submitted)
        ->and($webRequisition->lines->first()->qty_requested)->toBe(4);

    // API submission
    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/requisitions', [
            'notes' => 'Submitted via API',
            'lines' => [
                ['product_id' => $product->id, 'qty_requested' => 4],
            ],
        ])
        ->assertCreated();

    $apiRequisition = Requisition::query()->where('notes', 'Submitted via API')->firstOrFail();
    expect($apiRequisition->status)->toBe(RequisitionStatus::Submitted)
        ->and($apiRequisition->lines->first()->qty_requested)->toBe(4);

    // Both must have audit log records
    $this->assertDatabaseHas('audit_logs', [
        'model_type' => 'Requisition',
        'model_id' => $webRequisition->id,
        'action' => 'create',
    ]);

    $this->assertDatabaseHas('audit_logs', [
        'model_type' => 'Requisition',
        'model_id' => $apiRequisition->id,
        'action' => 'create',
    ]);
});

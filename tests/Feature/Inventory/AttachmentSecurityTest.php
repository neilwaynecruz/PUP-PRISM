<?php

use App\Models\Asset;
use App\Models\Attachment;
use App\Models\InventoryCountSession;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->withoutVite();
    foreach (['Admin', 'Supply Head', 'Property Custodian'] as $role) {
        Role::findOrCreate($role);
    }
    Storage::fake('local');
});

test('authorized personnel can upload and download a privately stored document', function () {
    $custodian = User::factory()->create();
    $custodian->assignRole('Property Custodian');
    $asset = Asset::factory()->create();
    $file = UploadedFile::fake()->createWithContent('inspection.pdf', "%PDF-1.4\nPhase 4 test document");

    $this->actingAs($custodian)
        ->post(route('inventory.attachments.store', ['asset', $asset->id], absolute: false), [
            'title' => 'Annual inspection report',
            'category' => 'inspection',
            'file' => $file,
        ])
        ->assertRedirect();

    $attachment = Attachment::query()->firstOrFail();
    Storage::disk('local')->assertExists($attachment->path);
    expect($attachment->path)->not->toContain('inspection.pdf')
        ->and($attachment->disk)->toBe('local');

    $this->actingAs($custodian)
        ->get(route('inventory.attachments.download', $attachment, absolute: false))
        ->assertOk()
        ->assertDownload('inspection.pdf');
});

test('invalid and oversized uploads are rejected', function () {
    $admin = User::factory()->withTwoFactor()->create();
    $admin->assignRole('Admin');
    $asset = Asset::factory()->create();

    $this->actingAs($admin)
        ->post(route('inventory.attachments.store', ['asset', $asset->id], absolute: false), [
            'title' => 'Executable',
            'category' => 'other',
            'file' => UploadedFile::fake()->create('malware.exe', 10, 'application/x-msdownload'),
        ])
        ->assertSessionHasErrors('file');

    $this->actingAs($admin)
        ->post(route('inventory.attachments.store', ['asset', $asset->id], absolute: false), [
            'title' => 'Large report',
            'category' => 'other',
            'file' => UploadedFile::fake()->create('large.pdf', 10 * 1024 + 1, 'application/pdf'),
        ])
        ->assertSessionHasErrors('file');

    expect(Attachment::query()->count())->toBe(0);
});

test('users without an inventory role cannot download attachments', function () {
    $uploader = User::factory()->create();
    $uploader->assignRole('Admin');
    $outsider = User::factory()->create();
    $attachment = Attachment::factory()->create([
        'attachable_id' => Asset::factory(),
        'uploaded_by' => $uploader->id,
    ]);
    Storage::disk('local')->put($attachment->path, 'private document');

    $this->actingAs($outsider)
        ->get(route('inventory.attachments.download', $attachment, absolute: false))
        ->assertForbidden();
});

test('removing an attachment preserves the private file for retention', function () {
    $admin = User::factory()->withTwoFactor()->create();
    $admin->assignRole('Admin');
    $attachment = Attachment::factory()->create(['uploaded_by' => $admin->id]);
    Storage::disk('local')->put($attachment->path, 'retained document');

    $this->actingAs($admin)
        ->delete(route('inventory.attachments.destroy', $attachment, absolute: false))
        ->assertRedirect();

    expect(Attachment::query()->find($attachment->id))->toBeNull()
        ->and(Attachment::withTrashed()->find($attachment->id)?->deleted_at)->not->toBeNull();
    Storage::disk('local')->assertExists($attachment->path);
});

test('repeated uploads of the same document do not create duplicate records', function () {
    $admin = User::factory()->withTwoFactor()->create();
    $admin->assignRole('Admin');
    $asset = Asset::factory()->create();
    $payload = [
        'title' => 'Duplicate inspection report',
        'category' => 'inspection',
    ];

    foreach ([1, 2] as $attempt) {
        $response = $this->actingAs($admin)
            ->post(route('inventory.attachments.store', ['asset', $asset->id], absolute: false), [
                ...$payload,
                'file' => UploadedFile::fake()->createWithContent("../inspection-{$attempt}.pdf", "%PDF-1.4\nidentical document"),
            ]);

        $attempt === 1
            ? $response->assertRedirect()->assertSessionHasNoErrors()
            : $response->assertSessionHasErrors('file');
    }

    expect(Attachment::query()->count())->toBe(1)
        ->and(Attachment::query()->firstOrFail()->path)->not->toContain('..');
});

test('missing private files return not found without exposing a storage path', function () {
    $admin = User::factory()->withTwoFactor()->create();
    $admin->assignRole('Admin');
    $attachment = Attachment::factory()->create(['uploaded_by' => $admin->id]);

    $this->actingAs($admin)
        ->get(route('inventory.attachments.download', $attachment, absolute: false))
        ->assertNotFound();
});

test('count participants cannot download documents from unrelated count sessions', function () {
    $uploader = User::factory()->create();
    $uploader->assignRole('Supply Head');
    $otherCounter = User::factory()->withTwoFactor()->create();
    $otherCounter->assignRole('Supply Head');
    $session = InventoryCountSession::factory()->create([
        'created_by' => $uploader->id,
        'assigned_to' => $uploader->id,
    ]);
    $attachment = Attachment::factory()->create([
        'attachable_type' => InventoryCountSession::class,
        'attachable_id' => $session->id,
        'uploaded_by' => $uploader->id,
    ]);
    Storage::disk('local')->put($attachment->path, 'private count document');

    $this->actingAs($otherCounter)
        ->get(route('inventory.attachments.download', $attachment, absolute: false))
        ->assertForbidden();
});

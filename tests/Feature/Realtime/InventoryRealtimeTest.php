<?php

use App\Events\InventoryRealtimeMessage;
use App\Models\Position;
use App\Models\Requisition;
use App\Models\User;
use App\Services\NotificationService;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;

uses()->group('realtime');

beforeEach(function () {
    Notification::fake();
    Event::fake([InventoryRealtimeMessage::class]);

    (new RoleSeeder)->run();
});

test('notification service dispatches a realtime requisition event', function () {
    $supplyHead = User::factory()->create();
    $supplyHead->assignRole('Supply Head');

    $requester = User::factory()->create([
        'position_id' => Position::factory()->create()->id,
    ]);

    $requisition = Requisition::factory()->create([
        'requester_id' => $requester->id,
    ]);

    app(NotificationService::class)->requisitionSubmitted($requisition);

    Event::assertDispatched(InventoryRealtimeMessage::class, function (InventoryRealtimeMessage $event) use ($requisition) {
        return $event->entity === 'requisition'
            && $event->action === 'submitted'
            && $event->context['requisition_id'] === $requisition->id
            && $event->modules === ['dashboard', 'requisitions', 'audit-logs']
            && $event->roles === ['admin', 'supply-head'];
    });
});

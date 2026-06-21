<?php

namespace App\Services;

use App\Events\InventoryRealtimeMessage;
use App\Models\Booking;
use App\Models\HandoverLog;
use App\Models\Product;
use App\Models\Requisition;

class InventoryRealtimeService
{
    private const ROLE_ADMIN = 'admin';

    private const ROLE_SUPPLY_HEAD = 'supply-head';

    private const ROLE_PROPERTY_CUSTODIAN = 'property-custodian';

    public function requisitionSubmitted(Requisition $requisition): void
    {
        $this->dispatch(
            entity: 'requisition',
            action: 'submitted',
            title: __('New requisition submitted'),
            message: __('Requisition #:id is ready for review.', ['id' => $requisition->id]),
            roles: [self::ROLE_ADMIN, self::ROLE_SUPPLY_HEAD],
            modules: ['dashboard', 'requisitions', 'audit-logs'],
            url: route('inventory.requisitions.show', $requisition, absolute: false),
            context: [
                'requisition_id' => $requisition->id,
                'requester_id' => $requisition->requester_id,
                'status' => $requisition->status->value,
            ],
        );
    }

    public function requisitionStatusChanged(Requisition $requisition, string $action): void
    {
        $this->dispatch(
            entity: 'requisition',
            action: $action,
            title: __('Requisition updated'),
            message: __('Requisition #:id was :action.', ['id' => $requisition->id, 'action' => $action]),
            roles: [self::ROLE_ADMIN, self::ROLE_SUPPLY_HEAD],
            modules: ['dashboard', 'requisitions', 'audit-logs'],
            url: route('inventory.requisitions.show', $requisition, absolute: false),
            context: [
                'requisition_id' => $requisition->id,
                'requester_id' => $requisition->requester_id,
                'status' => $requisition->status->value,
            ],
        );
    }

    public function bookingSubmitted(Booking $booking): void
    {
        $this->dispatch(
            entity: 'booking',
            action: 'submitted',
            title: __('New booking request'),
            message: __('Booking #:id is waiting for review.', ['id' => $booking->id]),
            roles: [self::ROLE_ADMIN, self::ROLE_PROPERTY_CUSTODIAN],
            modules: ['dashboard', 'bookings', 'audit-logs'],
            url: route('inventory.bookings.show', $booking->id, absolute: false),
            context: [
                'booking_id' => $booking->id,
                'requester_id' => $booking->requester_id,
                'status' => $booking->status->value,
            ],
        );
    }

    public function bookingStatusChanged(Booking $booking, string $action): void
    {
        $this->dispatch(
            entity: 'booking',
            action: $action,
            title: __('Booking updated'),
            message: __('Booking #:id was :action.', ['id' => $booking->id, 'action' => $action]),
            roles: [self::ROLE_ADMIN, self::ROLE_PROPERTY_CUSTODIAN],
            modules: ['dashboard', 'bookings', 'audit-logs'],
            url: route('inventory.bookings.show', $booking->id, absolute: false),
            context: [
                'booking_id' => $booking->id,
                'requester_id' => $booking->requester_id,
                'status' => $booking->status->value,
            ],
        );
    }

    public function stockReceived(Product $product, ?int $onHandQuantity, int $delta): void
    {
        $this->dispatch(
            entity: 'stock',
            action: 'received',
            title: __('Stock received'),
            message: __('Inventory for :sku was increased.', ['sku' => $product->sku]),
            roles: [self::ROLE_ADMIN, self::ROLE_SUPPLY_HEAD],
            modules: ['dashboard', 'products', 'receiving', 'movements', 'audit-logs'],
            url: route('inventory.products.show', $product, absolute: false),
            context: [
                'product_id' => $product->id,
                'sku' => $product->sku,
                'delta' => $delta,
                'on_hand_qty' => $onHandQuantity,
            ],
        );
    }

    public function stockIssued(Product $product, int $onHandQuantity, int $delta, ?int $requisitionId = null): void
    {
        $this->dispatch(
            entity: 'stock',
            action: 'issued',
            title: __('Stock issued'),
            message: __('Inventory for :sku was decreased.', ['sku' => $product->sku]),
            roles: [self::ROLE_ADMIN, self::ROLE_SUPPLY_HEAD],
            modules: ['dashboard', 'products', 'requisitions', 'movements', 'audit-logs'],
            url: route('inventory.products.show', $product, absolute: false),
            context: [
                'product_id' => $product->id,
                'sku' => $product->sku,
                'delta' => $delta,
                'on_hand_qty' => $onHandQuantity,
                'requisition_id' => $requisitionId,
            ],
        );
    }

    public function handoverInitiated(HandoverLog $handoverLog): void
    {
        $this->dispatch(
            entity: 'handover',
            action: 'initiated',
            title: __('Asset handover initiated'),
            message: __('A handover verification request was sent.'),
            roles: [self::ROLE_ADMIN, self::ROLE_PROPERTY_CUSTODIAN],
            modules: ['dashboard', 'handover', 'audit-logs'],
            url: route('inventory.handover.index', absolute: false),
            context: [
                'handover_log_id' => $handoverLog->id,
                'asset_id' => $handoverLog->asset_id,
                'to_user_id' => $handoverLog->to_user_id,
            ],
        );
    }

    public function handoverVerified(HandoverLog $handoverLog): void
    {
        $this->dispatch(
            entity: 'handover',
            action: 'verified',
            title: __('Asset handover verified'),
            message: __('A handover has been verified and accountability was updated.'),
            roles: [self::ROLE_ADMIN, self::ROLE_PROPERTY_CUSTODIAN],
            modules: ['dashboard', 'handover', 'movements', 'audit-logs'],
            url: route('inventory.handover.receipt', $handoverLog, absolute: false),
            context: [
                'handover_log_id' => $handoverLog->id,
                'asset_id' => $handoverLog->asset_id,
                'verified_by' => $handoverLog->verified_by,
            ],
        );
    }

    /**
     * @param  list<string>  $roles
     * @param  list<string>  $modules
     * @param  array<string, mixed>  $context
     */
    private function dispatch(
        string $entity,
        string $action,
        string $title,
        string $message,
        array $roles,
        array $modules,
        ?string $url = null,
        array $context = [],
    ): void {
        event(new InventoryRealtimeMessage(
            entity: $entity,
            action: $action,
            title: $title,
            message: $message,
            roles: $roles,
            modules: $modules,
            url: $url,
            context: $context,
        ));
    }
}

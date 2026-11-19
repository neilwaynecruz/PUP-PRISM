<?php

use App\Enums\RequisitionStatus;
use App\Models\Requisition;

test('requisition status constraint rollback preserves open requisitions as approved', function () {
    withPostgresTestingDatabase(function (): void {
        $partial = Requisition::factory()->create(['status' => RequisitionStatus::PartiallyIssued]);
        $backordered = Requisition::factory()->create(['status' => RequisitionStatus::Backordered]);
        $issued = Requisition::factory()->create(['status' => RequisitionStatus::Issued]);

        $migration = require base_path('database/migrations/2026_07_04_025311_expand_requisition_status_workflow.php');
        $migration->down();

        expect($partial->fresh()->status)->toBe(RequisitionStatus::Approved)
            ->and($backordered->fresh()->status)->toBe(RequisitionStatus::Approved)
            ->and($issued->fresh()->status)->toBe(RequisitionStatus::Issued);

        $migration->up();

        $partial->refresh();
        $backordered->refresh();

        $partial->forceFill(['status' => RequisitionStatus::PartiallyIssued])->save();
        $backordered->forceFill(['status' => RequisitionStatus::Backordered])->save();

        expect($partial->fresh()?->status)->toBe(RequisitionStatus::PartiallyIssued)
            ->and($backordered->fresh()?->status)->toBe(RequisitionStatus::Backordered);
    });
});

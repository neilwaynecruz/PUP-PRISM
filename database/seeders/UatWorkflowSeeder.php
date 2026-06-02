<?php

namespace Database\Seeders;

use App\Models\Asset;
use App\Models\Booking;
use App\Models\HandoverLog;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\Requisition;
use App\Models\RequisitionLine;
use App\Models\StockLot;
use App\Models\StockMovement;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

class UatWorkflowSeeder extends Seeder
{
    public function run(): void
    {
        /** @var array<string, User> $users */
        $users = User::query()->get()->keyBy('email')->all();
        /** @var array<string, Asset> $assets */
        $assets = Asset::query()->get()->keyBy('tag_code')->all();
        /** @var array<string, Product> $products */
        $products = Product::query()->get()->keyBy('sku')->all();

        $tomorrowMorning = CarbonImmutable::now()->addDay()->setTime(9, 0);

        Booking::query()->updateOrCreate(
            [
                'asset_id' => $assets['AST-DIR-0001']->id,
                'start_at' => $tomorrowMorning,
                'end_at' => $tomorrowMorning->addHours(3),
            ],
            [
                'requester_id' => $users['director@local.test']->id,
                'requester_position_id' => $users['director@local.test']->position_id,
                'approver_id' => $users['custodian@local.test']->id,
                'approver_position_id' => $users['custodian@local.test']->position_id,
                'requested_ip_address' => '10.250.8.10',
                'approved_ip_address' => '10.250.8.11',
                'status' => 'Approved',
                'purpose' => '[UAT] Director briefing and equipment walkthrough',
            ],
        );

        Booking::query()->updateOrCreate(
            [
                'asset_id' => $assets['AST-STU-0001']->id,
                'start_at' => $tomorrowMorning->addDays(1),
                'end_at' => $tomorrowMorning->addDays(1)->addHours(2),
            ],
            [
                'requester_id' => $users['library.custodian@local.test']->id,
                'requester_position_id' => $users['library.custodian@local.test']->position_id,
                'approver_id' => null,
                'approver_position_id' => null,
                'requested_ip_address' => '10.250.8.12',
                'approved_ip_address' => null,
                'status' => 'Requested',
                'purpose' => '[UAT] Library digital orientation',
            ],
        );

        Booking::query()->updateOrCreate(
            [
                'asset_id' => $assets['AST-LIB-0001']->id,
                'start_at' => $tomorrowMorning->addDays(2),
                'end_at' => $tomorrowMorning->addDays(2)->addHours(2),
            ],
            [
                'requester_id' => $users['eng.custodian@local.test']->id,
                'requester_position_id' => $users['eng.custodian@local.test']->position_id,
                'approver_id' => $users['custodian@local.test']->id,
                'approver_position_id' => $users['custodian@local.test']->position_id,
                'requested_ip_address' => '10.250.8.13',
                'approved_ip_address' => '10.250.8.11',
                'status' => 'Rejected',
                'purpose' => '[UAT] Conflicting booking example',
            ],
        );

        $approvedRequisition = Requisition::query()->updateOrCreate(
            ['notes' => '[UAT] Pending library supplies requisition'],
            [
                'requester_id' => $users['library.custodian@local.test']->id,
                'requester_position_id' => $users['library.custodian@local.test']->position_id,
                'approver_id' => $users['supply@local.test']->id,
                'approver_position_id' => $users['supply@local.test']->position_id,
                'requested_ip_address' => '10.250.8.14',
                'approved_ip_address' => '10.250.8.21',
                'approved_at' => CarbonImmutable::now()->subDay(),
                'issued_by' => null,
                'issued_position_id' => null,
                'issued_ip_address' => null,
                'issued_at' => null,
                'status' => 'Approved',
            ],
        );

        $this->syncRequisitionLine($approvedRequisition, $products['CON-PAPER-A4'], 10, 0);
        $this->syncRequisitionLine($approvedRequisition, $products['CON-INK-BLK'], 2, 0);

        $submittedRequisition = Requisition::query()->updateOrCreate(
            ['notes' => '[UAT] Submitted director office sanitation request'],
            [
                'requester_id' => $users['director.office.custodian@local.test']->id,
                'requester_position_id' => $users['director.office.custodian@local.test']->position_id,
                'approver_id' => null,
                'approver_position_id' => null,
                'requested_ip_address' => '10.250.8.15',
                'approved_ip_address' => null,
                'approved_at' => null,
                'issued_by' => null,
                'issued_position_id' => null,
                'issued_ip_address' => null,
                'issued_at' => null,
                'status' => 'Submitted',
            ],
        );

        $this->syncRequisitionLine($submittedRequisition, $products['CON-ALCOHOL-70'], 4, 0);

        $issuedRequisition = Requisition::query()->updateOrCreate(
            ['notes' => '[UAT] Issued engineering field kit replenishment'],
            [
                'requester_id' => $users['eng.custodian@local.test']->id,
                'requester_position_id' => $users['eng.custodian@local.test']->position_id,
                'approver_id' => $users['supply@local.test']->id,
                'approver_position_id' => $users['supply@local.test']->position_id,
                'requested_ip_address' => '10.250.8.16',
                'approved_ip_address' => '10.250.8.21',
                'approved_at' => CarbonImmutable::now()->subDays(2),
                'issued_by' => $users['supply@local.test']->id,
                'issued_position_id' => $users['supply@local.test']->position_id,
                'issued_ip_address' => '10.250.8.21',
                'issued_at' => CarbonImmutable::now()->subDays(1),
                'status' => 'Issued',
            ],
        );

        $this->syncRequisitionLine($issuedRequisition, $products['CON-BAT-AA'], 6, 6);
        $this->syncRequisitionLine($issuedRequisition, $products['CON-DISINF-01'], 2, 2);

        ProductStock::query()
            ->where('product_id', $products['CON-BAT-AA']->id)
            ->update(['on_hand_qty' => 39]);

        ProductStock::query()
            ->where('product_id', $products['CON-DISINF-01']->id)
            ->update(['on_hand_qty' => 16]);

        StockLot::query()
            ->where('product_id', $products['CON-BAT-AA']->id)
            ->where('reference_no', 'UAT-BAT-001')
            ->update(['qty_remaining' => 14]);

        StockLot::query()
            ->where('product_id', $products['CON-DISINF-01']->id)
            ->where('reference_no', 'UAT-DIS-001')
            ->update(['qty_remaining' => 16]);

        $batteryLot = StockLot::query()->where('product_id', $products['CON-BAT-AA']->id)->where('reference_no', 'UAT-BAT-001')->firstOrFail();
        $disinfectantLot = StockLot::query()->where('product_id', $products['CON-DISINF-01']->id)->where('reference_no', 'UAT-DIS-001')->firstOrFail();

        StockMovement::query()->updateOrCreate(
            [
                'movement_type' => 'issue',
                'requisition_id' => $issuedRequisition->id,
                'product_id' => $products['CON-BAT-AA']->id,
                'stock_lot_id' => $batteryLot->id,
            ],
            [
                'asset_id' => null,
                'qty_delta' => -6,
                'performed_by' => $users['supply@local.test']->id,
                'accountable_position_id' => $issuedRequisition->requester_position_id,
                'ip_address' => '10.250.8.21',
                'performed_at' => CarbonImmutable::now()->subDays(1),
                'notes' => '[UAT] Issued engineering field kit replenishment',
            ],
        );

        StockMovement::query()->updateOrCreate(
            [
                'movement_type' => 'issue',
                'requisition_id' => $issuedRequisition->id,
                'product_id' => $products['CON-DISINF-01']->id,
                'stock_lot_id' => $disinfectantLot->id,
            ],
            [
                'asset_id' => null,
                'qty_delta' => -2,
                'performed_by' => $users['supply@local.test']->id,
                'accountable_position_id' => $issuedRequisition->requester_position_id,
                'ip_address' => '10.250.8.21',
                'performed_at' => CarbonImmutable::now()->subDays(1),
                'notes' => '[UAT] Issued engineering field kit replenishment',
            ],
        );

        $handover = HandoverLog::query()->updateOrCreate(
            ['notes' => '[UAT] Engineering laptop accountability handover'],
            [
                'asset_id' => $assets['AST-COE-0001']->id,
                'from_user_id' => $users['custodian@local.test']->id,
                'to_user_id' => $users['eng.custodian@local.test']->id,
                'from_position_id' => $users['custodian@local.test']->position_id,
                'to_position_id' => $users['eng.custodian@local.test']->position_id,
                'initiated_by' => $users['custodian@local.test']->id,
                'initiated_at' => CarbonImmutable::now()->subDays(3),
                'verified_at' => CarbonImmutable::now()->subDays(3)->addHours(1),
                'verified_by' => $users['eng.custodian@local.test']->id,
                'verification_token_hash' => null,
                'ip_address' => '10.250.8.18',
                'verified_ip_address' => '10.250.8.19',
                'signature_png' => null,
            ],
        );

        $assets['AST-COE-0001']->update([
            'position_id' => $users['eng.custodian@local.test']->position_id,
            'status' => 'Checked_Out',
        ]);

        StockMovement::query()->updateOrCreate(
            [
                'movement_type' => 'transfer',
                'asset_id' => $assets['AST-COE-0001']->id,
                'notes' => '[UAT] Engineering laptop accountability handover',
            ],
            [
                'product_id' => $assets['AST-COE-0001']->product_id,
                'stock_lot_id' => null,
                'requisition_id' => null,
                'qty_delta' => null,
                'performed_by' => $users['eng.custodian@local.test']->id,
                'accountable_position_id' => $handover->to_position_id,
                'ip_address' => '10.250.8.19',
                'performed_at' => CarbonImmutable::now()->subDays(3)->addHours(1),
            ],
        );
    }

    protected function syncRequisitionLine(Requisition $requisition, Product $product, int $qtyRequested, int $qtyIssued): void
    {
        RequisitionLine::query()->updateOrCreate(
            [
                'requisition_id' => $requisition->id,
                'product_id' => $product->id,
            ],
            [
                'qty_requested' => $qtyRequested,
                'qty_issued' => $qtyIssued,
            ],
        );
    }
}

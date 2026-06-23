<?php

namespace Database\Seeders;

use App\Enums\AssetStatus;
use App\Enums\BookingStatus;
use App\Enums\RequisitionStatus;
use App\Models\Asset;
use App\Models\Booking;
use App\Models\HandoverLog;
use App\Models\Product;
use App\Models\Requisition;
use App\Models\RequisitionLine;
use App\Models\StockMovement;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

class UatWorkflowSeeder extends Seeder
{
    public function run(): void
    {
        $timeline = CarbonImmutable::today()->setTime(11, 0);
        /** @var array<string, User> $users */
        $users = User::query()->get()->keyBy('email')->all();
        /** @var array<string, Asset> $assets */
        $assets = Asset::query()->get()->keyBy('tag_code')->all();
        /** @var array<string, Product> $products */
        $products = Product::withTrashed()->get()->keyBy('sku')->all();

        $tomorrowMorning = $timeline->addDay()->setTime(9, 0);

        $approvedBooking = Booking::query()->updateOrCreate(
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
                'status' => BookingStatus::Approved,
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
                'status' => BookingStatus::Requested,
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
                'status' => BookingStatus::Rejected,
                'purpose' => '[UAT] Conflicting booking example',
            ],
        );

        Booking::query()->updateOrCreate(
            [
                'asset_id' => $assets['AST-SPMO-0001']->id,
                'start_at' => $tomorrowMorning->addDays(3),
                'end_at' => $tomorrowMorning->addDays(3)->addHours(3),
            ],
            [
                'requester_id' => $users['admin.office.custodian@local.test']->id,
                'requester_position_id' => $users['admin.office.custodian@local.test']->position_id,
                'approver_id' => $users['custodian@local.test']->id,
                'approver_position_id' => $users['custodian@local.test']->position_id,
                'requested_ip_address' => '10.250.8.20',
                'approved_ip_address' => '10.250.8.11',
                'status' => BookingStatus::Cancelled,
                'purpose' => '[UAT] Cancelled records management demo',
            ],
        );

        $deletedBooking = Booking::withTrashed()->updateOrCreate(
            [
                'asset_id' => $assets['AST-REG-0001']->id,
                'start_at' => $tomorrowMorning->subDays(2),
                'end_at' => $tomorrowMorning->subDays(2)->addHours(4),
            ],
            [
                'requester_id' => $users['registrar.custodian@local.test']->id,
                'requester_position_id' => $users['registrar.custodian@local.test']->position_id,
                'approver_id' => $users['custodian@local.test']->id,
                'approver_position_id' => $users['custodian@local.test']->position_id,
                'requested_ip_address' => '10.250.8.30',
                'approved_ip_address' => '10.250.8.11',
                'status' => BookingStatus::Approved,
                'purpose' => '[UAT] Deleted registrar equipment checkout',
            ],
        );
        $this->softDeleteBooking($deletedBooking, $users['admin@local.test'], 'Seeded trash sample for booking recovery workflows.');

        $approvedRequisition = Requisition::query()->updateOrCreate(
            ['notes' => '[UAT] Pending library supplies requisition'],
            [
                'requester_id' => $users['library.custodian@local.test']->id,
                'requester_position_id' => $users['library.custodian@local.test']->position_id,
                'approver_id' => $users['supply@local.test']->id,
                'approver_position_id' => $users['supply@local.test']->position_id,
                'requested_ip_address' => '10.250.8.14',
                'approved_ip_address' => '10.250.8.21',
                'approved_at' => $timeline->subDay(),
                'issued_by' => null,
                'issued_position_id' => null,
                'issued_ip_address' => null,
                'issued_at' => null,
                'status' => RequisitionStatus::Approved,
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
                'status' => RequisitionStatus::Submitted,
            ],
        );
        $this->syncRequisitionLine($submittedRequisition, $products['CON-ALCOHOL-70'], 4, 0);
        $this->syncRequisitionLine($submittedRequisition, $products['CON-GLOVES-M'], 3, 0);

        $issuedRequisition = Requisition::query()->updateOrCreate(
            ['notes' => '[UAT] Issued engineering field kit replenishment'],
            [
                'requester_id' => $users['eng.custodian@local.test']->id,
                'requester_position_id' => $users['eng.custodian@local.test']->position_id,
                'approver_id' => $users['supply@local.test']->id,
                'approver_position_id' => $users['supply@local.test']->position_id,
                'requested_ip_address' => '10.250.8.16',
                'approved_ip_address' => '10.250.8.21',
                'approved_at' => $timeline->subDays(2),
                'issued_by' => $users['supply@local.test']->id,
                'issued_position_id' => $users['supply@local.test']->position_id,
                'issued_ip_address' => '10.250.8.21',
                'issued_at' => $timeline->subDay(),
                'status' => RequisitionStatus::Issued,
            ],
        );
        $this->syncRequisitionLine($issuedRequisition, $products['CON-BAT-AA'], 6, 6);
        $this->syncRequisitionLine($issuedRequisition, $products['CON-DISINF-01'], 2, 2);

        $draftRequisition = Requisition::query()->updateOrCreate(
            ['notes' => '[UAT] Draft administration office supply pack'],
            [
                'requester_id' => $users['admin.office.custodian@local.test']->id,
                'requester_position_id' => $users['admin.office.custodian@local.test']->position_id,
                'approver_id' => null,
                'approver_position_id' => null,
                'requested_ip_address' => '10.250.8.17',
                'approved_ip_address' => null,
                'approved_at' => null,
                'issued_by' => null,
                'issued_position_id' => null,
                'issued_ip_address' => null,
                'issued_at' => null,
                'status' => RequisitionStatus::Draft,
            ],
        );
        $this->syncRequisitionLine($draftRequisition, $products['CON-PAPER-A4'], 5, 0);
        $this->syncRequisitionLine($draftRequisition, $products['CON-FILEBOX-01'], 2, 0);

        $closedRequisition = Requisition::query()->updateOrCreate(
            ['notes' => '[UAT] Closed monthly office restock'],
            [
                'requester_id' => $users['admin.office.custodian@local.test']->id,
                'requester_position_id' => $users['admin.office.custodian@local.test']->position_id,
                'approver_id' => $users['supply@local.test']->id,
                'approver_position_id' => $users['supply@local.test']->position_id,
                'requested_ip_address' => '10.250.8.18',
                'approved_ip_address' => '10.250.8.21',
                'approved_at' => $timeline->subDays(12),
                'issued_by' => $users['supply@local.test']->id,
                'issued_position_id' => $users['supply@local.test']->position_id,
                'issued_ip_address' => '10.250.8.21',
                'issued_at' => $timeline->subDays(11),
                'status' => RequisitionStatus::Closed,
            ],
        );
        $this->syncRequisitionLine($closedRequisition, $products['CON-PAPER-A4'], 15, 15);
        $this->syncRequisitionLine($closedRequisition, $products['CON-INK-BLK'], 4, 4);

        $rejectedRequisition = Requisition::query()->updateOrCreate(
            ['notes' => '[UAT] Rejected student affairs stock request'],
            [
                'requester_id' => $users['student.affairs.custodian@local.test']->id,
                'requester_position_id' => $users['student.affairs.custodian@local.test']->position_id,
                'approver_id' => $users['supply@local.test']->id,
                'approver_position_id' => $users['supply@local.test']->position_id,
                'requested_ip_address' => '10.250.8.19',
                'approved_ip_address' => '10.250.8.21',
                'approved_at' => $timeline->subDays(5),
                'issued_by' => null,
                'issued_position_id' => null,
                'issued_ip_address' => null,
                'issued_at' => null,
                'status' => RequisitionStatus::Rejected,
            ],
        );
        $this->syncRequisitionLine($rejectedRequisition, $products['CON-GLOVES-M'], 5, 0);

        $deletedRequisition = Requisition::withTrashed()->updateOrCreate(
            ['notes' => '[UAT] Deleted registrar emergency request'],
            [
                'requester_id' => $users['registrar.custodian@local.test']->id,
                'requester_position_id' => $users['registrar.custodian@local.test']->position_id,
                'approver_id' => null,
                'approver_position_id' => null,
                'requested_ip_address' => '10.250.8.31',
                'approved_ip_address' => null,
                'approved_at' => null,
                'issued_by' => null,
                'issued_position_id' => null,
                'issued_ip_address' => null,
                'issued_at' => null,
                'status' => RequisitionStatus::Draft,
            ],
        );
        $this->syncRequisitionLine($deletedRequisition, $products['CON-ALCOHOL-70'], 6, 0);
        $this->softDeleteRequisition($deletedRequisition, $users['admin@local.test'], 'Seeded trash sample for requisition restore coverage.');

        $this->seedConsumableLedger(
            $products['CON-PAPER-A4'],
            [
                ['performed_at' => $timeline->subDays(74), 'qty_delta' => -18, 'movement_type' => 'issue', 'performed_by' => $users['supply@local.test'], 'accountable_position_id' => $users['library.custodian@local.test']->position_id, 'notes' => '[UAT] Semester printing support'],
                ['performed_at' => $timeline->subDays(63), 'qty_delta' => -20, 'movement_type' => 'issue', 'performed_by' => $users['supply@local.test'], 'accountable_position_id' => $users['eng.custodian@local.test']->position_id, 'notes' => '[UAT] Engineering handout production'],
                ['performed_at' => $timeline->subDays(49), 'qty_delta' => -22, 'movement_type' => 'issue', 'performed_by' => $users['supply@local.test'], 'accountable_position_id' => $users['director.office.custodian@local.test']->position_id, 'notes' => '[UAT] Director office report run'],
                ['performed_at' => $timeline->subDays(36), 'qty_delta' => -18, 'movement_type' => 'issue', 'performed_by' => $users['supply@local.test'], 'accountable_position_id' => $users['admin.office.custodian@local.test']->position_id, 'notes' => '[UAT] Administration packet release'],
                ['performed_at' => $timeline->subDays(30), 'qty_delta' => 4, 'movement_type' => 'return', 'performed_by' => $users['admin.office.custodian@local.test'], 'accountable_position_id' => $users['admin.office.custodian@local.test']->position_id, 'notes' => '[UAT] Unused stock returned from orientation kits'],
                ['performed_at' => $timeline->subDays(22), 'qty_delta' => -26, 'movement_type' => 'issue', 'performed_by' => $users['supply@local.test'], 'accountable_position_id' => $users['library.custodian@local.test']->position_id, 'notes' => '[UAT] Midterm examination printing'],
                ['performed_at' => $timeline->subDays(12), 'qty_delta' => -15, 'movement_type' => 'issue', 'performed_by' => $users['supply@local.test'], 'accountable_position_id' => $closedRequisition->requester_position_id, 'notes' => '[UAT] Closed monthly office restock', 'requisition_id' => $closedRequisition->id],
                ['performed_at' => $timeline->subDays(8), 'qty_delta' => -21, 'movement_type' => 'issue', 'performed_by' => $users['supply@local.test'], 'accountable_position_id' => $users['eng.custodian@local.test']->position_id, 'notes' => '[UAT] Laboratory manual duplication'],
                ['performed_at' => $timeline->subDays(3), 'qty_delta' => -34, 'movement_type' => 'issue', 'performed_by' => $users['supply@local.test'], 'accountable_position_id' => $users['student.affairs.custodian@local.test']->position_id, 'notes' => '[UAT] Clearance form issuance'],
            ],
        );

        $this->seedConsumableLedger(
            $products['CON-INK-BLK'],
            [
                ['performed_at' => $timeline->subDays(70), 'qty_delta' => -4, 'movement_type' => 'issue', 'performed_by' => $users['supply@local.test'], 'accountable_position_id' => $users['library.custodian@local.test']->position_id, 'notes' => '[UAT] Public printer refill'],
                ['performed_at' => $timeline->subDays(54), 'qty_delta' => -5, 'movement_type' => 'issue', 'performed_by' => $users['supply@local.test'], 'accountable_position_id' => $users['registrar.custodian@local.test']->position_id, 'notes' => '[UAT] Registrar printer refill'],
                ['performed_at' => $timeline->subDays(39), 'qty_delta' => -3, 'movement_type' => 'issue', 'performed_by' => $users['supply@local.test'], 'accountable_position_id' => $users['director.office.custodian@local.test']->position_id, 'notes' => '[UAT] Office draft packets'],
                ['performed_at' => $timeline->subDays(33), 'qty_delta' => 1, 'movement_type' => 'return', 'performed_by' => $users['director.office.custodian@local.test'], 'accountable_position_id' => $users['director.office.custodian@local.test']->position_id, 'notes' => '[UAT] Unopened spare cartridge returned'],
                ['performed_at' => $timeline->subDays(21), 'qty_delta' => -6, 'movement_type' => 'issue', 'performed_by' => $users['supply@local.test'], 'accountable_position_id' => $users['admin.office.custodian@local.test']->position_id, 'notes' => '[UAT] Enrollment document printing'],
                ['performed_at' => $timeline->subDays(12), 'qty_delta' => -4, 'movement_type' => 'issue', 'performed_by' => $users['supply@local.test'], 'accountable_position_id' => $closedRequisition->requester_position_id, 'notes' => '[UAT] Closed monthly office restock', 'requisition_id' => $closedRequisition->id],
                ['performed_at' => $timeline->subDays(5), 'qty_delta' => -7, 'movement_type' => 'issue', 'performed_by' => $users['supply@local.test'], 'accountable_position_id' => $users['library.custodian@local.test']->position_id, 'notes' => '[UAT] Week-end report printing'],
            ],
        );

        $this->seedConsumableLedger(
            $products['CON-ALCOHOL-70'],
            [
                ['performed_at' => $timeline->subDays(69), 'qty_delta' => -5, 'movement_type' => 'issue', 'performed_by' => $users['supply@local.test'], 'accountable_position_id' => $users['student.affairs.custodian@local.test']->position_id, 'notes' => '[UAT] Campus activity sanitation pack'],
                ['performed_at' => $timeline->subDays(58), 'qty_delta' => -4, 'movement_type' => 'issue', 'performed_by' => $users['supply@local.test'], 'accountable_position_id' => $users['library.custodian@local.test']->position_id, 'notes' => '[UAT] Library sanitation refill'],
                ['performed_at' => $timeline->subDays(44), 'qty_delta' => -6, 'movement_type' => 'issue', 'performed_by' => $users['supply@local.test'], 'accountable_position_id' => $users['eng.custodian@local.test']->position_id, 'notes' => '[UAT] Engineering field kit sanitation'],
                ['performed_at' => $timeline->subDays(31), 'qty_delta' => -7, 'movement_type' => 'issue', 'performed_by' => $users['supply@local.test'], 'accountable_position_id' => $users['director.office.custodian@local.test']->position_id, 'notes' => '[UAT] Executive meeting room sanitation'],
                ['performed_at' => $timeline->subDays(27), 'qty_delta' => 2, 'movement_type' => 'return', 'performed_by' => $users['director.office.custodian@local.test'], 'accountable_position_id' => $users['director.office.custodian@local.test']->position_id, 'notes' => '[UAT] Unused sanitation bottles returned'],
                ['performed_at' => $timeline->subDays(15), 'qty_delta' => -8, 'movement_type' => 'issue', 'performed_by' => $users['supply@local.test'], 'accountable_position_id' => $users['admin.office.custodian@local.test']->position_id, 'notes' => '[UAT] Administration sanitation station refill'],
                ['performed_at' => $timeline->subDays(6), 'qty_delta' => -8, 'movement_type' => 'issue', 'performed_by' => $users['supply@local.test'], 'accountable_position_id' => $users['library.custodian@local.test']->position_id, 'notes' => '[UAT] Library event preparation'],
            ],
        );

        $this->seedConsumableLedger(
            $products['CON-BAT-AA'],
            [
                ['performed_at' => $timeline->subDays(76), 'qty_delta' => -4, 'movement_type' => 'issue', 'performed_by' => $users['supply@local.test'], 'accountable_position_id' => $users['student.affairs.custodian@local.test']->position_id, 'notes' => '[UAT] Student affairs microphone batteries'],
                ['performed_at' => $timeline->subDays(59), 'qty_delta' => -5, 'movement_type' => 'issue', 'performed_by' => $users['supply@local.test'], 'accountable_position_id' => $users['director.office.custodian@local.test']->position_id, 'notes' => '[UAT] Conference remote battery replacement'],
                ['performed_at' => $timeline->subDays(41), 'qty_delta' => -3, 'movement_type' => 'issue', 'performed_by' => $users['supply@local.test'], 'accountable_position_id' => $users['library.custodian@local.test']->position_id, 'notes' => '[UAT] Library wireless mouse replacement'],
                ['performed_at' => $timeline->subDays(26), 'qty_delta' => -4, 'movement_type' => 'issue', 'performed_by' => $users['supply@local.test'], 'accountable_position_id' => $users['admin.office.custodian@local.test']->position_id, 'notes' => '[UAT] Administration presentation remotes'],
                ['performed_at' => $timeline->subDays(9), 'qty_delta' => -4, 'movement_type' => 'issue', 'performed_by' => $users['supply@local.test'], 'accountable_position_id' => $users['eng.custodian@local.test']->position_id, 'notes' => '[UAT] Field tester power cells'],
                ['performed_at' => $timeline->subDay(), 'qty_delta' => -6, 'movement_type' => 'issue', 'performed_by' => $users['supply@local.test'], 'accountable_position_id' => $issuedRequisition->requester_position_id, 'notes' => '[UAT] Issued engineering field kit replenishment', 'requisition_id' => $issuedRequisition->id],
            ],
        );

        $this->seedConsumableLedger(
            $products['CON-DISINF-01'],
            [
                ['performed_at' => $timeline->subDays(51), 'qty_delta' => -3, 'movement_type' => 'issue', 'performed_by' => $users['supply@local.test'], 'accountable_position_id' => $users['student.affairs.custodian@local.test']->position_id, 'notes' => '[UAT] Event space disinfection'],
                ['performed_at' => $timeline->subDays(38), 'qty_delta' => -4, 'movement_type' => 'issue', 'performed_by' => $users['supply@local.test'], 'accountable_position_id' => $users['library.custodian@local.test']->position_id, 'notes' => '[UAT] Library shelf sanitation'],
                ['performed_at' => $timeline->subDays(24), 'qty_delta' => -5, 'movement_type' => 'issue', 'performed_by' => $users['supply@local.test'], 'accountable_position_id' => $users['admin.office.custodian@local.test']->position_id, 'notes' => '[UAT] Front desk sanitation rotation'],
                ['performed_at' => $timeline->subDays(13), 'qty_delta' => -4, 'movement_type' => 'issue', 'performed_by' => $users['supply@local.test'], 'accountable_position_id' => $users['director.office.custodian@local.test']->position_id, 'notes' => '[UAT] Director office sanitation cycle'],
                ['performed_at' => $timeline->subDays(7), 'qty_delta' => -4, 'movement_type' => 'issue', 'performed_by' => $users['supply@local.test'], 'accountable_position_id' => $users['eng.custodian@local.test']->position_id, 'notes' => '[UAT] Engineering laboratory wipe-down'],
                ['performed_at' => $timeline->subDay(), 'qty_delta' => -2, 'movement_type' => 'issue', 'performed_by' => $users['supply@local.test'], 'accountable_position_id' => $issuedRequisition->requester_position_id, 'notes' => '[UAT] Issued engineering field kit replenishment', 'requisition_id' => $issuedRequisition->id],
            ],
        );

        $this->seedConsumableLedger(
            $products['CON-GLOVES-M'],
            [
                ['performed_at' => $timeline->subDays(18), 'qty_delta' => -2, 'movement_type' => 'issue', 'performed_by' => $users['supply@local.test'], 'accountable_position_id' => $users['library.custodian@local.test']->position_id, 'notes' => '[UAT] Library receiving gloves'],
                ['performed_at' => $timeline->subDays(14), 'qty_delta' => -3, 'movement_type' => 'issue', 'performed_by' => $users['supply@local.test'], 'accountable_position_id' => $users['student.affairs.custodian@local.test']->position_id, 'notes' => '[UAT] Event setup safety kit'],
                ['performed_at' => $timeline->subDays(10), 'qty_delta' => -2, 'movement_type' => 'issue', 'performed_by' => $users['supply@local.test'], 'accountable_position_id' => $users['admin.office.custodian@local.test']->position_id, 'notes' => '[UAT] Mailroom safety replenishment'],
                ['performed_at' => $timeline->subDays(4), 'qty_delta' => -5, 'movement_type' => 'issue', 'performed_by' => $users['supply@local.test'], 'accountable_position_id' => $users['director.office.custodian@local.test']->position_id, 'notes' => '[UAT] Cleaning response deployment'],
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
                'initiated_at' => $timeline->subDays(3),
                'verified_at' => $timeline->subDays(3)->addHour(),
                'verified_by' => $users['eng.custodian@local.test']->id,
                'verification_token_hash' => null,
                'ip_address' => '10.250.8.18',
                'verified_ip_address' => '10.250.8.19',
                'signature_png' => null,
            ],
        );

        $assets['AST-COE-0001']->update([
            'position_id' => $users['eng.custodian@local.test']->position_id,
            'status' => AssetStatus::CheckedOut,
        ]);

        StockMovement::query()->updateOrCreate(
            [
                'movement_type' => 'transfer',
                'asset_id' => $assets['AST-COE-0001']->id,
                'performed_at' => $timeline->subDays(3)->addHour(),
            ],
            [
                'product_id' => $assets['AST-COE-0001']->product_id,
                'stock_lot_id' => null,
                'requisition_id' => null,
                'qty_delta' => null,
                'qty_before' => null,
                'qty_after' => null,
                'performed_by' => $users['eng.custodian@local.test']->id,
                'accountable_position_id' => $handover->to_position_id,
                'ip_address' => '10.250.8.19',
                'notes' => '[UAT] Engineering laptop accountability handover',
            ],
        );

        HandoverLog::query()->updateOrCreate(
            ['notes' => '[UAT] Pending director office projector handover'],
            [
                'asset_id' => $assets['AST-DIR-0001']->id,
                'from_user_id' => $users['custodian@local.test']->id,
                'to_user_id' => $users['director.office.custodian@local.test']->id,
                'from_position_id' => $users['custodian@local.test']->position_id,
                'to_position_id' => $users['director.office.custodian@local.test']->position_id,
                'initiated_by' => $users['custodian@local.test']->id,
                'initiated_at' => $timeline->subHours(5),
                'verified_at' => null,
                'verified_by' => null,
                'verification_token_hash' => hash('sha256', 'uat-preview-token'),
                'ip_address' => '10.250.8.40',
                'verified_ip_address' => null,
                'signature_png' => null,
            ],
        );

        StockMovement::query()->updateOrCreate(
            [
                'movement_type' => 'condemn',
                'asset_id' => $assets['AST-ADMIN-0002']->id,
                'performed_at' => $timeline->subDays(15),
            ],
            [
                'product_id' => $assets['AST-ADMIN-0002']->product_id,
                'stock_lot_id' => null,
                'requisition_id' => null,
                'qty_delta' => null,
                'qty_before' => null,
                'qty_after' => null,
                'performed_by' => $users['custodian@local.test']->id,
                'accountable_position_id' => $users['custodian@local.test']->position_id,
                'ip_address' => '10.250.8.41',
                'notes' => '[UAT] Asset condemned after hardware failure assessment',
            ],
        );

        $approvedBooking->touch();
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

    /**
     * @param  array<int, array{
     *     performed_at: CarbonImmutable,
     *     qty_delta: int,
     *     movement_type: string,
     *     performed_by: User,
     *     accountable_position_id: int|null,
     *     notes: string,
     *     requisition_id?: int|null
     * }>  $events
     */
    private function seedConsumableLedger(Product $product, array $events): void
    {
        $runningQty = (int) $product->lots()->sum('qty_received');

        usort($events, fn (array $left, array $right): int => $left['performed_at'] <=> $right['performed_at']);

        foreach ($events as $event) {
            $nextQty = $runningQty + $event['qty_delta'];

            if ($nextQty < 0) {
                throw new \RuntimeException("Seed ledger for {$product->sku} fell below zero.");
            }

            StockMovement::query()->updateOrCreate(
                [
                    'movement_type' => $event['movement_type'],
                    'product_id' => $product->id,
                    'performed_at' => $event['performed_at'],
                    'notes' => $event['notes'],
                ],
                [
                    'stock_lot_id' => null,
                    'asset_id' => null,
                    'requisition_id' => $event['requisition_id'] ?? null,
                    'qty_delta' => $event['qty_delta'],
                    'qty_before' => $runningQty,
                    'qty_after' => $nextQty,
                    'performed_by' => $event['performed_by']->id,
                    'accountable_position_id' => $event['accountable_position_id'],
                    'ip_address' => '10.250.8.21',
                ],
            );

            $runningQty = $nextQty;
        }

        $expectedFinalQty = (int) ($product->stock?->on_hand_qty ?? 0);

        if ($runningQty !== $expectedFinalQty) {
            throw new \RuntimeException("Seed ledger for {$product->sku} ended at {$runningQty}, expected {$expectedFinalQty}.");
        }
    }

    private function softDeleteBooking(Booking $booking, User $deletedBy, string $reason): void
    {
        if (! $booking->trashed()) {
            $booking->delete();
        }

        Booking::withTrashed()->whereKey($booking->id)->update([
            'deleted_at' => CarbonImmutable::today()->setTime(11, 0)->subDays(3),
            'deleted_by' => $deletedBy->id,
            'deletion_reason' => $reason,
        ]);
    }

    private function softDeleteRequisition(Requisition $requisition, User $deletedBy, string $reason): void
    {
        if (! $requisition->trashed()) {
            $requisition->delete();
        }

        Requisition::withTrashed()->whereKey($requisition->id)->update([
            'deleted_at' => CarbonImmutable::today()->setTime(11, 0)->subDays(4),
            'deleted_by' => $deletedBy->id,
            'deletion_reason' => $reason,
        ]);
    }
}

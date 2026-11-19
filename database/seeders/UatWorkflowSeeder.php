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
use App\Models\StockLot;
use App\Models\StockMovement;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\Concerns\SeedsConsumableLedger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;

class UatWorkflowSeeder extends Seeder
{
    use SeedsConsumableLedger;

    public function run(): void
    {
        if (app()->isProduction()) {
            throw new \RuntimeException('Operational seed data cannot be seeded in production.');
        }

        Model::unguarded(fn () => $this->seedWorkflows());
    }

    private function seedWorkflows(): void
    {

        $timeline = CarbonImmutable::today()->setTime(11, 0);
        /** @var array<string, User> $users */
        $users = User::query()->get()->keyBy('email')->all();
        /** @var array<string, Asset> $assets */
        $assets = Asset::query()->get()->keyBy('tag_code')->all();
        /** @var array<string, Product> $products */
        $products = Product::withTrashed()->with('stock')->get()->keyBy('sku')->all();

        $tomorrowMorning = $timeline->addDay()->setTime(9, 0);

        Booking::query()->updateOrCreate(
            ['purpose' => 'Director briefing and equipment walkthrough'],
            [
                'asset_id' => $assets['AST-DIR-0001']->id,
                'start_at' => $tomorrowMorning,
                'end_at' => $tomorrowMorning->addHours(3),
                'requester_id' => $users['cruzneilwayne@gmail.com']->id,
                'requester_position_id' => $users['cruzneilwayne@gmail.com']->position_id,
                'approver_id' => $users['neilwayne0514@gmail.com']->id,
                'approver_position_id' => $users['neilwayne0514@gmail.com']->position_id,
                'requested_ip_address' => '10.250.8.10',
                'approved_ip_address' => '10.250.8.11',
                'status' => BookingStatus::Approved,
            ],
        );

        Booking::query()->updateOrCreate(
            ['purpose' => 'Library digital orientation'],
            [
                'asset_id' => $assets['AST-STU-0001']->id,
                'start_at' => $tomorrowMorning->addDays(1),
                'end_at' => $tomorrowMorning->addDays(1)->addHours(2),
                'requester_id' => $users['nathanweiltcruz1415@gmail.com']->id,
                'requester_position_id' => $users['nathanweiltcruz1415@gmail.com']->position_id,
                'approver_id' => null,
                'approver_position_id' => null,
                'requested_ip_address' => '10.250.8.12',
                'approved_ip_address' => null,
                'status' => BookingStatus::Requested,
            ],
        );

        Booking::query()->updateOrCreate(
            ['purpose' => 'Engineering accreditation presentation'],
            [
                'asset_id' => $assets['AST-LIB-0001']->id,
                'start_at' => $tomorrowMorning->addDays(2),
                'end_at' => $tomorrowMorning->addDays(2)->addHours(2),
                'requester_id' => $users['nathanweiltcruz1415@gmail.com']->id,
                'requester_position_id' => $users['nathanweiltcruz1415@gmail.com']->position_id,
                'approver_id' => $users['neilwayne0514@gmail.com']->id,
                'approver_position_id' => $users['neilwayne0514@gmail.com']->position_id,
                'requested_ip_address' => '10.250.8.13',
                'approved_ip_address' => '10.250.8.11',
                'status' => BookingStatus::Rejected,
            ],
        );

        Booking::query()->updateOrCreate(
            ['purpose' => 'Records digitization workshop'],
            [
                'asset_id' => $assets['AST-SPMO-0001']->id,
                'start_at' => $tomorrowMorning->addDays(3),
                'end_at' => $tomorrowMorning->addDays(3)->addHours(3),
                'requester_id' => $users['wendzcruz04@gmail.com']->id,
                'requester_position_id' => $users['wendzcruz04@gmail.com']->position_id,
                'approver_id' => $users['neilwayne0514@gmail.com']->id,
                'approver_position_id' => $users['neilwayne0514@gmail.com']->position_id,
                'requested_ip_address' => '10.250.8.20',
                'approved_ip_address' => '10.250.8.11',
                'status' => BookingStatus::Cancelled,
            ],
        );

        $deletedBooking = Booking::withTrashed()->updateOrCreate(
            ['purpose' => 'Registrar enrollment support reservation'],
            [
                'asset_id' => $assets['AST-REG-0001']->id,
                'start_at' => $tomorrowMorning->subDays(2),
                'end_at' => $tomorrowMorning->subDays(2)->addHours(4),
                'requester_id' => $users['nathanweiltcruz1415@gmail.com']->id,
                'requester_position_id' => $users['nathanweiltcruz1415@gmail.com']->position_id,
                'approver_id' => $users['norman.cruz0701@gmail.com']->id,
                'approver_position_id' => $users['norman.cruz0701@gmail.com']->position_id,
                'requested_ip_address' => '10.250.8.30',
                'approved_ip_address' => '10.250.8.11',
                'status' => BookingStatus::Approved,
            ],
        );
        $this->softDeleteBooking($deletedBooking, $users['neilwayne0514@gmail.com'], 'Reservation withdrawn by the requesting office and retained for recovery records.');

        $approvedRequisition = Requisition::query()->updateOrCreate(
            ['notes' => 'Pending library supplies requisition'],
            [
                'requester_id' => $users['nathanweiltcruz1415@gmail.com']->id,
                'requester_position_id' => $users['nathanweiltcruz1415@gmail.com']->position_id,
                'approver_id' => $users['wendzcruz04@gmail.com']->id,
                'approver_position_id' => $users['wendzcruz04@gmail.com']->position_id,
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
            ['notes' => 'Submitted director office sanitation request'],
            [
                'requester_id' => $users['nathanweiltcruz1415@gmail.com']->id,
                'requester_position_id' => $users['nathanweiltcruz1415@gmail.com']->position_id,
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

        $partiallyIssuedRequisition = Requisition::query()->updateOrCreate(
            ['notes' => 'Clinic protective equipment replenishment partially issued'],
            [
                'requester_id' => $users['nathanweiltcruz1415@gmail.com']->id,
                'requester_position_id' => $users['nathanweiltcruz1415@gmail.com']->position_id,
                'approver_id' => $users['wendzcruz04@gmail.com']->id,
                'approver_position_id' => $users['wendzcruz04@gmail.com']->position_id,
                'requested_ip_address' => '10.250.8.22',
                'approved_ip_address' => '10.250.8.21',
                'approved_at' => $timeline->subDays(19),
                'issued_by' => $users['norman.cruz0701@gmail.com']->id,
                'issued_position_id' => $users['norman.cruz0701@gmail.com']->position_id,
                'issued_ip_address' => '10.250.8.21',
                'issued_at' => $timeline->subDays(18),
                'status' => RequisitionStatus::PartiallyIssued,
            ],
        );
        $this->syncRequisitionLine($partiallyIssuedRequisition, $products['CON-GLOVES-M'], 6, 2);

        $backorderedRequisition = Requisition::query()->updateOrCreate(
            ['notes' => 'Facilities washroom supplies awaiting replenishment'],
            [
                'requester_id' => $users['nathanweiltcruz1415@gmail.com']->id,
                'requester_position_id' => $users['nathanweiltcruz1415@gmail.com']->position_id,
                'approver_id' => $users['wendzcruz04@gmail.com']->id,
                'approver_position_id' => $users['wendzcruz04@gmail.com']->position_id,
                'requested_ip_address' => '10.250.8.23',
                'approved_ip_address' => '10.250.8.21',
                'approved_at' => $timeline->subDays(2),
                'issued_by' => null,
                'issued_position_id' => null,
                'issued_ip_address' => null,
                'issued_at' => null,
                'status' => RequisitionStatus::Backordered,
            ],
        );
        $this->syncRequisitionLine($backorderedRequisition, $products['CON-HSOAP-500'], 12, 0);

        $issuedRequisition = Requisition::query()->updateOrCreate(
            ['notes' => 'Issued engineering field kit replenishment'],
            [
                'requester_id' => $users['nathanweiltcruz1415@gmail.com']->id,
                'requester_position_id' => $users['nathanweiltcruz1415@gmail.com']->position_id,
                'approver_id' => $users['wendzcruz04@gmail.com']->id,
                'approver_position_id' => $users['wendzcruz04@gmail.com']->position_id,
                'requested_ip_address' => '10.250.8.16',
                'approved_ip_address' => '10.250.8.21',
                'approved_at' => $timeline->subDays(2),
                'issued_by' => $users['norman.cruz0701@gmail.com']->id,
                'issued_position_id' => $users['norman.cruz0701@gmail.com']->position_id,
                'issued_ip_address' => '10.250.8.21',
                'issued_at' => $timeline->subDay(),
                'status' => RequisitionStatus::Issued,
            ],
        );
        $this->syncRequisitionLine($issuedRequisition, $products['CON-BAT-AA'], 6, 6);
        $this->syncRequisitionLine($issuedRequisition, $products['CON-DISINF-01'], 2, 2);

        $draftRequisition = Requisition::query()->updateOrCreate(
            ['notes' => 'Draft administration office supply pack'],
            [
                'requester_id' => $users['cruzneilwayne@gmail.com']->id,
                'requester_position_id' => $users['cruzneilwayne@gmail.com']->position_id,
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
            ['notes' => 'Closed monthly office restock'],
            [
                'requester_id' => $users['cruzneilwayne@gmail.com']->id,
                'requester_position_id' => $users['cruzneilwayne@gmail.com']->position_id,
                'approver_id' => $users['wendzcruz04@gmail.com']->id,
                'approver_position_id' => $users['wendzcruz04@gmail.com']->position_id,
                'requested_ip_address' => '10.250.8.18',
                'approved_ip_address' => '10.250.8.21',
                'approved_at' => $timeline->subDays(12),
                'issued_by' => $users['norman.cruz0701@gmail.com']->id,
                'issued_position_id' => $users['norman.cruz0701@gmail.com']->position_id,
                'issued_ip_address' => '10.250.8.21',
                'issued_at' => $timeline->subDays(11),
                'status' => RequisitionStatus::Closed,
            ],
        );
        $this->syncRequisitionLine($closedRequisition, $products['CON-PAPER-A4'], 15, 15);
        $this->syncRequisitionLine($closedRequisition, $products['CON-INK-BLK'], 4, 4);

        $rejectedRequisition = Requisition::query()->updateOrCreate(
            ['notes' => 'Rejected student affairs stock request'],
            [
                'requester_id' => $users['nathanweiltcruz1415@gmail.com']->id,
                'requester_position_id' => $users['nathanweiltcruz1415@gmail.com']->position_id,
                'approver_id' => $users['wendzcruz04@gmail.com']->id,
                'approver_position_id' => $users['wendzcruz04@gmail.com']->position_id,
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
            ['notes' => 'Registrar emergency sanitation supplies'],
            [
                'requester_id' => $users['nathanweiltcruz1415@gmail.com']->id,
                'requester_position_id' => $users['nathanweiltcruz1415@gmail.com']->position_id,
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
        $this->softDeleteRequisition($deletedRequisition, $users['neilwayne0514@gmail.com'], 'Request withdrawn before submission and retained for recovery records.');

        $issuer = $users['norman.cruz0701@gmail.com'];
        $logisticsPosId = $users['nathanweiltcruz1415@gmail.com']->position_id;
        $procurementPosId = $users['wendzcruz04@gmail.com']->position_id;
        $itDirPosId = $users['cruzneilwayne@gmail.com']->position_id;
        $adminPosId = $users['neilwayne0514@gmail.com']->position_id;

        $historicalDemand = [
            'CON-PAPER-A4' => $this->seedHistoricalDemand(
                $products['CON-PAPER-A4'],
                $issuer,
                $logisticsPosId,
                $timeline,
                24,
                'A4P',
                'academic printing and examination requirements',
            ),
            'CON-INK-BLK' => $this->seedHistoricalDemand(
                $products['CON-INK-BLK'],
                $issuer,
                $itDirPosId,
                $timeline,
                5,
                'INK',
                'administrative printing and records processing',
            ),
            'CON-ALCOHOL-70' => $this->seedHistoricalDemand(
                $products['CON-ALCOHOL-70'],
                $issuer,
                $logisticsPosId,
                $timeline,
                7,
                'ALC',
                'campus sanitation and health stations',
                5,
            ),
            'CON-BAT-AA' => $this->seedHistoricalDemand(
                $products['CON-BAT-AA'],
                $issuer,
                $logisticsPosId,
                $timeline,
                5,
                'BAT',
                'classroom and laboratory equipment support',
                8,
            ),
            'CON-DISINF-01' => $this->seedHistoricalDemand(
                $products['CON-DISINF-01'],
                $issuer,
                $logisticsPosId,
                $timeline,
                5,
                'DSF',
                'facilities sanitation operations',
                5,
            ),
            'CON-GLOVES-M' => $this->seedHistoricalDemand(
                $products['CON-GLOVES-M'],
                $issuer,
                $logisticsPosId,
                $timeline,
                5,
                'GLV',
                'campus clinic and laboratory safety',
                5,
            ),
        ];

        $this->seedConsumableLedger(
            $products['CON-PAPER-A4'],
            [
                ...$historicalDemand['CON-PAPER-A4'],
                ['performed_at' => $timeline->subDays(86), 'qty_delta' => -22, 'movement_type' => 'issue', 'performed_by' => $issuer, 'accountable_position_id' => $itDirPosId, 'notes' => 'Academic printing allocation'],
                ['performed_at' => $timeline->subDays(79), 'qty_delta' => -20, 'movement_type' => 'issue', 'performed_by' => $issuer, 'accountable_position_id' => $logisticsPosId, 'notes' => 'Enrollment records print batch'],
                ['performed_at' => $timeline->subDays(74), 'qty_delta' => -18, 'movement_type' => 'issue', 'performed_by' => $issuer, 'accountable_position_id' => $logisticsPosId, 'notes' => 'Semester printing support'],
                ['performed_at' => $timeline->subDays(67), 'qty_delta' => -22, 'movement_type' => 'issue', 'performed_by' => $issuer, 'accountable_position_id' => $procurementPosId, 'notes' => 'Procurement bid packets run'],
                ['performed_at' => $timeline->subDays(63), 'qty_delta' => -20, 'movement_type' => 'issue', 'performed_by' => $issuer, 'accountable_position_id' => $logisticsPosId, 'notes' => 'Engineering handout production'],
                ['performed_at' => $timeline->subDays(56), 'qty_delta' => -24, 'movement_type' => 'issue', 'performed_by' => $issuer, 'accountable_position_id' => $adminPosId, 'notes' => 'Executive memorandum print batch'],
                ['performed_at' => $timeline->subDays(49), 'qty_delta' => -22, 'movement_type' => 'issue', 'performed_by' => $issuer, 'accountable_position_id' => $adminPosId, 'notes' => 'Director office report run'],
                ['performed_at' => $timeline->subDays(43), 'qty_delta' => -22, 'movement_type' => 'issue', 'performed_by' => $issuer, 'accountable_position_id' => $itDirPosId, 'notes' => 'IT manual and logbook print run'],
                ['performed_at' => $timeline->subDays(38), 'qty_delta' => -18, 'movement_type' => 'issue', 'performed_by' => $issuer, 'accountable_position_id' => $logisticsPosId, 'notes' => 'Departmental workbook duplication'],
                ['performed_at' => $timeline->subDays(36), 'qty_delta' => -18, 'movement_type' => 'issue', 'performed_by' => $issuer, 'accountable_position_id' => $procurementPosId, 'notes' => 'Administration packet release'],
                ['performed_at' => $timeline->subDays(33), 'qty_delta' => -10, 'movement_type' => 'issue', 'performed_by' => $issuer, 'accountable_position_id' => $logisticsPosId, 'notes' => 'Registrar bulk transcript batch'],
                ['performed_at' => $timeline->subDays(30), 'qty_delta' => 4, 'movement_type' => 'return', 'performed_by' => $users['wendzcruz04@gmail.com'], 'accountable_position_id' => $procurementPosId, 'notes' => 'Unused stock returned from orientation kits'],
                ['performed_at' => $timeline->subDays(28), 'qty_delta' => -8, 'movement_type' => 'issue', 'performed_by' => $issuer, 'accountable_position_id' => $logisticsPosId, 'notes' => 'Student handbook supplemental print run'],
                ['performed_at' => $timeline->subDays(25), 'qty_delta' => -8, 'movement_type' => 'issue', 'performed_by' => $issuer, 'accountable_position_id' => $itDirPosId, 'notes' => 'Faculty syllabus duplication batch'],
                ['performed_at' => $timeline->subDays(22), 'qty_delta' => -8, 'movement_type' => 'issue', 'performed_by' => $issuer, 'accountable_position_id' => $logisticsPosId, 'notes' => 'Midterm examination printing'],
                ['performed_at' => $timeline->subDays(19), 'qty_delta' => -6, 'movement_type' => 'issue', 'performed_by' => $issuer, 'accountable_position_id' => $adminPosId, 'notes' => 'College accreditation binder batch'],
                ['performed_at' => $timeline->subDays(16), 'qty_delta' => -6, 'movement_type' => 'issue', 'performed_by' => $issuer, 'accountable_position_id' => $procurementPosId, 'notes' => 'Campus memorandum distribution run'],
                ['performed_at' => $timeline->subDays(14), 'qty_delta' => -6, 'movement_type' => 'issue', 'performed_by' => $issuer, 'accountable_position_id' => $logisticsPosId, 'notes' => 'Departmental assessment sheet batch'],
                ['performed_at' => $timeline->subDays(12), 'qty_delta' => -15, 'movement_type' => 'issue', 'performed_by' => $issuer, 'accountable_position_id' => $closedRequisition->requester_position_id, 'notes' => 'Closed monthly office restock', 'requisition_id' => $closedRequisition->id],
                ['performed_at' => $timeline->subDays(9), 'qty_delta' => -4, 'movement_type' => 'issue', 'performed_by' => $issuer, 'accountable_position_id' => $procurementPosId, 'notes' => 'Procurement tender packet printing'],
                ['performed_at' => $timeline->subDays(8), 'qty_delta' => -5, 'movement_type' => 'issue', 'performed_by' => $issuer, 'accountable_position_id' => $logisticsPosId, 'notes' => 'Laboratory manual duplication'],
                ['performed_at' => $timeline->subDays(6), 'qty_delta' => -5, 'movement_type' => 'issue', 'performed_by' => $issuer, 'accountable_position_id' => $itDirPosId, 'notes' => 'Guidance testing forms production'],
                ['performed_at' => $timeline->subDays(5), 'qty_delta' => -6, 'movement_type' => 'issue', 'performed_by' => $issuer, 'accountable_position_id' => $itDirPosId, 'notes' => 'Registrar certified true copy run'],
                ['performed_at' => $timeline->subDays(4), 'qty_delta' => -6, 'movement_type' => 'issue', 'performed_by' => $issuer, 'accountable_position_id' => $adminPosId, 'notes' => 'Thesis defense booklet duplication'],
                ['performed_at' => $timeline->subDays(3), 'qty_delta' => -6, 'movement_type' => 'issue', 'performed_by' => $issuer, 'accountable_position_id' => $logisticsPosId, 'notes' => 'Clearance form issuance'],
                ['performed_at' => $timeline->subDays(2), 'qty_delta' => -6, 'movement_type' => 'issue', 'performed_by' => $issuer, 'accountable_position_id' => $procurementPosId, 'notes' => 'University procurement bid packet batch'],
                ['performed_at' => $timeline->subDay(), 'qty_delta' => -7, 'movement_type' => 'issue', 'performed_by' => $issuer, 'accountable_position_id' => $logisticsPosId, 'notes' => 'Daily campus print room allocation'],
            ],
        );

        $this->seedConsumableLedger(
            $products['CON-INK-BLK'],
            [
                ...$historicalDemand['CON-INK-BLK'],
                ['performed_at' => $timeline->subDays(82), 'qty_delta' => -3, 'movement_type' => 'issue', 'performed_by' => $issuer, 'accountable_position_id' => $itDirPosId, 'notes' => 'Registrar correspondence printer cartridge'],
                ['performed_at' => $timeline->subDays(70), 'qty_delta' => -4, 'movement_type' => 'issue', 'performed_by' => $issuer, 'accountable_position_id' => $logisticsPosId, 'notes' => 'Public printer refill'],
                ['performed_at' => $timeline->subDays(54), 'qty_delta' => -5, 'movement_type' => 'issue', 'performed_by' => $issuer, 'accountable_position_id' => $itDirPosId, 'notes' => 'Registrar printer refill'],
                ['performed_at' => $timeline->subDays(45), 'qty_delta' => -3, 'movement_type' => 'issue', 'performed_by' => $issuer, 'accountable_position_id' => $procurementPosId, 'notes' => 'Procurement office printer maintenance'],
                ['performed_at' => $timeline->subDays(39), 'qty_delta' => -3, 'movement_type' => 'issue', 'performed_by' => $issuer, 'accountable_position_id' => $adminPosId, 'notes' => 'Office draft packets'],
                ['performed_at' => $timeline->subDays(33), 'qty_delta' => 1, 'movement_type' => 'return', 'performed_by' => $users['neilwayne0514@gmail.com'], 'accountable_position_id' => $adminPosId, 'notes' => 'Unopened spare cartridge returned'],
                ['performed_at' => $timeline->subDays(21), 'qty_delta' => -1, 'movement_type' => 'issue', 'performed_by' => $issuer, 'accountable_position_id' => $procurementPosId, 'notes' => 'Enrollment document printing'],
                ['performed_at' => $timeline->subDays(19), 'qty_delta' => -1, 'movement_type' => 'issue', 'performed_by' => $issuer, 'accountable_position_id' => $adminPosId, 'notes' => 'Cashier billing printer refill'],
                ['performed_at' => $timeline->subDays(16), 'qty_delta' => -1, 'movement_type' => 'issue', 'performed_by' => $issuer, 'accountable_position_id' => $itDirPosId, 'notes' => 'Admissions records printer refill'],
                ['performed_at' => $timeline->subDays(14), 'qty_delta' => -2, 'movement_type' => 'issue', 'performed_by' => $issuer, 'accountable_position_id' => $logisticsPosId, 'notes' => 'Dean office laserjet backup refill'],
                ['performed_at' => $timeline->subDays(12), 'qty_delta' => -4, 'movement_type' => 'issue', 'performed_by' => $issuer, 'accountable_position_id' => $closedRequisition->requester_position_id, 'notes' => 'Closed monthly office restock', 'requisition_id' => $closedRequisition->id],
                ['performed_at' => $timeline->subDays(10), 'qty_delta' => -2, 'movement_type' => 'issue', 'performed_by' => $issuer, 'accountable_position_id' => $logisticsPosId, 'notes' => 'Property inventory labeling station'],
                ['performed_at' => $timeline->subDays(9), 'qty_delta' => -2, 'movement_type' => 'issue', 'performed_by' => $issuer, 'accountable_position_id' => $logisticsPosId, 'notes' => 'Registrar certificate station refill'],
                ['performed_at' => $timeline->subDays(8), 'qty_delta' => -2, 'movement_type' => 'issue', 'performed_by' => $issuer, 'accountable_position_id' => $adminPosId, 'notes' => 'Guidance office voucher printing'],
                ['performed_at' => $timeline->subDays(7), 'qty_delta' => -2, 'movement_type' => 'issue', 'performed_by' => $issuer, 'accountable_position_id' => $procurementPosId, 'notes' => 'Procurement bid compliance printer refill'],
                ['performed_at' => $timeline->subDays(6), 'qty_delta' => -2, 'movement_type' => 'issue', 'performed_by' => $issuer, 'accountable_position_id' => $procurementPosId, 'notes' => 'Academic senate packet printing'],
                ['performed_at' => $timeline->subDays(5), 'qty_delta' => -2, 'movement_type' => 'issue', 'performed_by' => $issuer, 'accountable_position_id' => $logisticsPosId, 'notes' => 'Week-end report printing'],
                ['performed_at' => $timeline->subDays(4), 'qty_delta' => -2, 'movement_type' => 'issue', 'performed_by' => $issuer, 'accountable_position_id' => $adminPosId, 'notes' => 'Finance disbursements counter swap'],
                ['performed_at' => $timeline->subDays(3), 'qty_delta' => -2, 'movement_type' => 'issue', 'performed_by' => $issuer, 'accountable_position_id' => $adminPosId, 'notes' => 'Payroll slip printer station refill'],
                ['performed_at' => $timeline->subDays(2), 'qty_delta' => -3, 'movement_type' => 'issue', 'performed_by' => $issuer, 'accountable_position_id' => $itDirPosId, 'notes' => 'College lab heavy batch refill'],
                ['performed_at' => $timeline->subDay(), 'qty_delta' => -3, 'movement_type' => 'issue', 'performed_by' => $issuer, 'accountable_position_id' => $itDirPosId, 'notes' => 'Daily front-desk printer swap'],
            ],
        );

        $this->seedConsumableLedger(
            $products['CON-ALCOHOL-70'],
            [
                ...$historicalDemand['CON-ALCOHOL-70'],
                ['performed_at' => $timeline->subDays(80), 'qty_delta' => -4, 'movement_type' => 'issue', 'performed_by' => $issuer, 'accountable_position_id' => $logisticsPosId, 'notes' => 'Campus gate triage station refill'],
                ['performed_at' => $timeline->subDays(69), 'qty_delta' => -5, 'movement_type' => 'issue', 'performed_by' => $issuer, 'accountable_position_id' => $logisticsPosId, 'notes' => 'Campus activity sanitation pack'],
                ['performed_at' => $timeline->subDays(58), 'qty_delta' => -4, 'movement_type' => 'issue', 'performed_by' => $issuer, 'accountable_position_id' => $itDirPosId, 'notes' => 'Library sanitation refill'],
                ['performed_at' => $timeline->subDays(44), 'qty_delta' => -6, 'movement_type' => 'issue', 'performed_by' => $issuer, 'accountable_position_id' => $logisticsPosId, 'notes' => 'Engineering field kit sanitation'],
                ['performed_at' => $timeline->subDays(31), 'qty_delta' => -4, 'movement_type' => 'issue', 'performed_by' => $issuer, 'accountable_position_id' => $adminPosId, 'notes' => 'Executive meeting room sanitation'],
                ['performed_at' => $timeline->subDays(27), 'qty_delta' => 2, 'movement_type' => 'return', 'performed_by' => $users['neilwayne0514@gmail.com'], 'accountable_position_id' => $adminPosId, 'notes' => 'Unused sanitation bottles returned'],
                ['performed_at' => $timeline->subDays(22), 'qty_delta' => -3, 'movement_type' => 'issue', 'performed_by' => $issuer, 'accountable_position_id' => $logisticsPosId, 'notes' => 'College of Science laboratory hand hygiene'],
                ['performed_at' => $timeline->subDays(18), 'qty_delta' => -2, 'movement_type' => 'issue', 'performed_by' => $issuer, 'accountable_position_id' => $procurementPosId, 'notes' => 'Registrar counter dispenser refill'],
                ['performed_at' => $timeline->subDays(15), 'qty_delta' => -3, 'movement_type' => 'issue', 'performed_by' => $issuer, 'accountable_position_id' => $procurementPosId, 'notes' => 'Administration sanitation station refill'],
                ['performed_at' => $timeline->subDays(11), 'qty_delta' => -2, 'movement_type' => 'issue', 'performed_by' => $issuer, 'accountable_position_id' => $logisticsPosId, 'notes' => 'Student lounge hygiene station refill'],
                ['performed_at' => $timeline->subDays(8), 'qty_delta' => -2, 'movement_type' => 'issue', 'performed_by' => $issuer, 'accountable_position_id' => $adminPosId, 'notes' => 'Main lobby reception station refill'],
                ['performed_at' => $timeline->subDays(6), 'qty_delta' => -2, 'movement_type' => 'issue', 'performed_by' => $issuer, 'accountable_position_id' => $itDirPosId, 'notes' => 'Library event preparation'],
                ['performed_at' => $timeline->subDays(2), 'qty_delta' => -1, 'movement_type' => 'issue', 'performed_by' => $issuer, 'accountable_position_id' => $logisticsPosId, 'notes' => 'Clinic exam room dispenser top-up'],
            ],
        );

        $this->seedConsumableLedger(
            $products['CON-BAT-AA'],
            [
                ...$historicalDemand['CON-BAT-AA'],
                ['performed_at' => $timeline->subDays(80), 'qty_delta' => -4, 'movement_type' => 'issue', 'performed_by' => $issuer, 'accountable_position_id' => $itDirPosId, 'notes' => 'IT server rack tester batteries'],
                ['performed_at' => $timeline->subDays(76), 'qty_delta' => -4, 'movement_type' => 'issue', 'performed_by' => $issuer, 'accountable_position_id' => $logisticsPosId, 'notes' => 'Student affairs microphone batteries'],
                ['performed_at' => $timeline->subDays(66), 'qty_delta' => -4, 'movement_type' => 'issue', 'performed_by' => $issuer, 'accountable_position_id' => $itDirPosId, 'notes' => 'Classroom smart-podium clicker pool'],
                ['performed_at' => $timeline->subDays(59), 'qty_delta' => -5, 'movement_type' => 'issue', 'performed_by' => $issuer, 'accountable_position_id' => $adminPosId, 'notes' => 'Conference remote battery replacement'],
                ['performed_at' => $timeline->subDays(48), 'qty_delta' => -4, 'movement_type' => 'issue', 'performed_by' => $issuer, 'accountable_position_id' => $logisticsPosId, 'notes' => 'Gymnasium PA handheld mic replenishment'],
                ['performed_at' => $timeline->subDays(41), 'qty_delta' => -3, 'movement_type' => 'issue', 'performed_by' => $issuer, 'accountable_position_id' => $itDirPosId, 'notes' => 'Library wireless mouse replacement'],
                ['performed_at' => $timeline->subDays(26), 'qty_delta' => -3, 'movement_type' => 'issue', 'performed_by' => $issuer, 'accountable_position_id' => $procurementPosId, 'notes' => 'Administration presentation remotes'],
                ['performed_at' => $timeline->subDays(16), 'qty_delta' => -2, 'movement_type' => 'issue', 'performed_by' => $issuer, 'accountable_position_id' => $logisticsPosId, 'notes' => 'Examination room wall clock swap'],
                ['performed_at' => $timeline->subDays(14), 'qty_delta' => -2, 'movement_type' => 'issue', 'performed_by' => $issuer, 'accountable_position_id' => $itDirPosId, 'notes' => 'Laboratory digital multimeter cells'],
                ['performed_at' => $timeline->subDays(12), 'qty_delta' => -2, 'movement_type' => 'issue', 'performed_by' => $issuer, 'accountable_position_id' => $logisticsPosId, 'notes' => 'Audio-visual booth wireless lapel mics'],
                ['performed_at' => $timeline->subDays(9), 'qty_delta' => -4, 'movement_type' => 'issue', 'performed_by' => $issuer, 'accountable_position_id' => $logisticsPosId, 'notes' => 'Field tester power cells'],
                ['performed_at' => $timeline->subDays(7), 'qty_delta' => -2, 'movement_type' => 'issue', 'performed_by' => $issuer, 'accountable_position_id' => $adminPosId, 'notes' => 'Portable projector remote battery replacement'],
                ['performed_at' => $timeline->subDays(5), 'qty_delta' => -2, 'movement_type' => 'issue', 'performed_by' => $issuer, 'accountable_position_id' => $logisticsPosId, 'notes' => 'Campus patrol flashlight cells'],
                ['performed_at' => $timeline->subDays(3), 'qty_delta' => -2, 'movement_type' => 'issue', 'performed_by' => $issuer, 'accountable_position_id' => $logisticsPosId, 'notes' => 'Seminar hall microphone receiver swap'],
                ['performed_at' => $timeline->subDay(), 'qty_delta' => -6, 'movement_type' => 'issue', 'performed_by' => $issuer, 'accountable_position_id' => $issuedRequisition->requester_position_id, 'notes' => 'Issued engineering field kit replenishment', 'requisition_id' => $issuedRequisition->id],
            ],
        );

        $this->seedConsumableLedger(
            $products['CON-DISINF-01'],
            [
                ...$historicalDemand['CON-DISINF-01'],
                ['performed_at' => $timeline->subDays(62), 'qty_delta' => -2, 'movement_type' => 'issue', 'performed_by' => $issuer, 'accountable_position_id' => $logisticsPosId, 'notes' => 'Annex building corridor sanitization'],
                ['performed_at' => $timeline->subDays(51), 'qty_delta' => -3, 'movement_type' => 'issue', 'performed_by' => $issuer, 'accountable_position_id' => $logisticsPosId, 'notes' => 'Event space disinfection'],
                ['performed_at' => $timeline->subDays(44), 'qty_delta' => -2, 'movement_type' => 'issue', 'performed_by' => $issuer, 'accountable_position_id' => $logisticsPosId, 'notes' => 'Restroom custodial bay restock'],
                ['performed_at' => $timeline->subDays(38), 'qty_delta' => -3, 'movement_type' => 'issue', 'performed_by' => $issuer, 'accountable_position_id' => $itDirPosId, 'notes' => 'Library shelf sanitation'],
                ['performed_at' => $timeline->subDays(24), 'qty_delta' => -2, 'movement_type' => 'issue', 'performed_by' => $issuer, 'accountable_position_id' => $procurementPosId, 'notes' => 'Front desk sanitation rotation'],
                ['performed_at' => $timeline->subDays(19), 'qty_delta' => -2, 'movement_type' => 'issue', 'performed_by' => $issuer, 'accountable_position_id' => $logisticsPosId, 'notes' => 'Auditorium seating disinfection cycle'],
                ['performed_at' => $timeline->subDays(13), 'qty_delta' => -2, 'movement_type' => 'issue', 'performed_by' => $issuer, 'accountable_position_id' => $adminPosId, 'notes' => 'Director office sanitation cycle'],
                ['performed_at' => $timeline->subDays(7), 'qty_delta' => -2, 'movement_type' => 'issue', 'performed_by' => $issuer, 'accountable_position_id' => $logisticsPosId, 'notes' => 'Engineering laboratory wipe-down'],
                ['performed_at' => $timeline->subDays(4), 'qty_delta' => -2, 'movement_type' => 'issue', 'performed_by' => $issuer, 'accountable_position_id' => $logisticsPosId, 'notes' => 'Ground floor clinic disinfection mopping'],
                ['performed_at' => $timeline->subDay(), 'qty_delta' => -2, 'movement_type' => 'issue', 'performed_by' => $issuer, 'accountable_position_id' => $issuedRequisition->requester_position_id, 'notes' => 'Issued engineering field kit replenishment', 'requisition_id' => $issuedRequisition->id],
            ],
        );

        $this->seedConsumableLedger(
            $products['CON-GLOVES-M'],
            [
                ...$historicalDemand['CON-GLOVES-M'],
                ['performed_at' => $timeline->subDays(18), 'qty_delta' => -2, 'movement_type' => 'issue', 'performed_by' => $issuer, 'accountable_position_id' => $partiallyIssuedRequisition->requester_position_id, 'notes' => 'Clinic protective equipment replenishment partially issued', 'requisition_id' => $partiallyIssuedRequisition->id],
                ['performed_at' => $timeline->subDays(14), 'qty_delta' => -2, 'movement_type' => 'issue', 'performed_by' => $issuer, 'accountable_position_id' => $logisticsPosId, 'notes' => 'Event setup safety kit'],
                ['performed_at' => $timeline->subDays(10), 'qty_delta' => -2, 'movement_type' => 'issue', 'performed_by' => $issuer, 'accountable_position_id' => $logisticsPosId, 'notes' => 'Chemistry prep lab PPE allocation'],
                ['performed_at' => $timeline->subDays(6), 'qty_delta' => -2, 'movement_type' => 'issue', 'performed_by' => $issuer, 'accountable_position_id' => $procurementPosId, 'notes' => 'Mailroom safety replenishment'],
                ['performed_at' => $timeline->subDays(4), 'qty_delta' => -1, 'movement_type' => 'issue', 'performed_by' => $issuer, 'accountable_position_id' => $logisticsPosId, 'notes' => 'Facilities plumbing crew protective gear'],
                ['performed_at' => $timeline->subDays(2), 'qty_delta' => -1, 'movement_type' => 'issue', 'performed_by' => $issuer, 'accountable_position_id' => $procurementPosId, 'notes' => 'Clinic isolation unit PPE refill'],
                ['performed_at' => $timeline->subDay(), 'qty_delta' => -2, 'movement_type' => 'issue', 'performed_by' => $issuer, 'accountable_position_id' => $adminPosId, 'notes' => 'Cleaning response deployment'],
            ],
        );

        $handover = HandoverLog::query()->updateOrCreate(
            ['notes' => 'Engineering laptop accountability handover'],
            [
                'asset_id' => $assets['AST-COE-0001']->id,
                'from_user_id' => $users['cruzneilwayne@gmail.com']->id,
                'to_user_id' => $users['nathanweiltcruz1415@gmail.com']->id,
                'from_position_id' => $users['cruzneilwayne@gmail.com']->position_id,
                'to_position_id' => $users['nathanweiltcruz1415@gmail.com']->position_id,
                'initiated_by' => $users['cruzneilwayne@gmail.com']->id,
                'initiated_at' => $timeline->subDays(3),
                'verified_at' => $timeline->subDays(3)->addHour(),
                'verified_by' => $users['nathanweiltcruz1415@gmail.com']->id,
                'verification_token_hash' => null,
                'ip_address' => '10.250.8.18',
                'verified_ip_address' => '10.250.8.19',
                'signature_png' => null,
            ],
        );

        $assets['AST-COE-0001']->update([
            'position_id' => $users['nathanweiltcruz1415@gmail.com']->position_id,
            'status' => AssetStatus::CheckedOut,
        ]);

        StockMovement::query()->updateOrCreate(
            [
                'movement_type' => 'transfer',
                'asset_id' => $assets['AST-COE-0001']->id,
                'notes' => 'Engineering laptop accountability handover',
            ],
            [
                'product_id' => $assets['AST-COE-0001']->product_id,
                'stock_lot_id' => null,
                'requisition_id' => null,
                'qty_delta' => null,
                'qty_before' => null,
                'qty_after' => null,
                'performed_by' => $users['nathanweiltcruz1415@gmail.com']->id,
                'accountable_position_id' => $handover->to_position_id,
                'ip_address' => '10.250.8.19',
                'performed_at' => $timeline->subDays(3)->addHour(),
            ],
        );

        HandoverLog::query()->updateOrCreate(
            ['notes' => 'Pending director office projector handover'],
            [
                'asset_id' => $assets['AST-DIR-0001']->id,
                'from_user_id' => $users['neilwayne0514@gmail.com']->id,
                'to_user_id' => $users['nathanweiltcruz1415@gmail.com']->id,
                'from_position_id' => $users['neilwayne0514@gmail.com']->position_id,
                'to_position_id' => $users['nathanweiltcruz1415@gmail.com']->position_id,
                'initiated_by' => $users['neilwayne0514@gmail.com']->id,
                'initiated_at' => $timeline->subHours(5),
                'verified_at' => null,
                'verified_by' => null,
                'verification_token_hash' => hash('sha256', 'pending-projector-handover-token'),
                'ip_address' => '10.250.8.40',
                'verified_ip_address' => null,
                'signature_png' => null,
            ],
        );

        StockMovement::query()->updateOrCreate(
            [
                'movement_type' => 'condemn',
                'asset_id' => $assets['AST-ADMIN-0002']->id,
                'notes' => 'Asset condemned after hardware failure assessment',
            ],
            [
                'product_id' => $assets['AST-ADMIN-0002']->product_id,
                'stock_lot_id' => null,
                'requisition_id' => null,
                'qty_delta' => null,
                'qty_before' => null,
                'qty_after' => null,
                'performed_by' => $users['norman.cruz0701@gmail.com']->id,
                'accountable_position_id' => $users['neilwayne0514@gmail.com']->position_id,
                'ip_address' => '10.250.8.41',
                'performed_at' => $timeline->subDays(15),
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

    /**
     * Seed four years of monthly demand backed by quarterly delivery lots.
     *
     * @return list<array{
     *     performed_at: CarbonImmutable,
     *     qty_delta: int,
     *     movement_type: string,
     *     performed_by: User,
     *     accountable_position_id: int|null,
     *     notes: string
     * }>
     */
    private function seedHistoricalDemand(
        Product $product,
        User $performedBy,
        ?int $accountablePositionId,
        CarbonImmutable $timeline,
        int $baseMonthlyDemand,
        string $referenceCode,
        string $activity,
        ?int $shelfLifeMonths = null,
    ): array {
        $seasonalPercentages = [
            1 => 110,
            2 => 95,
            3 => 105,
            4 => 90,
            5 => 75,
            6 => 85,
            7 => 125,
            8 => 120,
            9 => 105,
            10 => 110,
            11 => 95,
            12 => 70,
        ];
        $issueNotePrefix = "Monthly {$activity} allocation - ";
        $deliveryReferences = [];
        $events = [];
        $quarterlyDemand = [];

        for ($monthsAgo = 51; $monthsAgo >= 4; $monthsAgo--) {
            $month = $timeline->startOfMonth()->subMonths($monthsAgo);
            $trendPercentage = $monthsAgo > 24 ? 90 : ($monthsAgo > 12 ? 100 : 110);
            $quantity = max(
                1,
                (int) round($baseMonthlyDemand * $seasonalPercentages[$month->month] * $trendPercentage / 10_000),
            );
            $quarterStart = $month->startOfQuarter();
            $quarterKey = $quarterStart->toDateString();

            $quarterlyDemand[$quarterKey] = ($quarterlyDemand[$quarterKey] ?? 0) + $quantity;
            $events[] = [
                'performed_at' => $month->day(15)->setTime(10, 0),
                'qty_delta' => -$quantity,
                'movement_type' => 'issue',
                'performed_by' => $performedBy,
                'accountable_position_id' => $accountablePositionId,
                'notes' => $issueNotePrefix.$month->format('F Y'),
            ];
        }

        foreach ($quarterlyDemand as $quarterDate => $quantity) {
            $quarterStart = CarbonImmutable::parse($quarterDate);
            $referenceNumber = sprintf('DR-%s-%s', $quarterStart->format('Ym'), $referenceCode);
            $deliveryReferences[] = $referenceNumber;
            $lot = StockLot::query()->updateOrCreate(
                [
                    'product_id' => $product->id,
                    'reference_no' => $referenceNumber,
                ],
                [
                    'received_at' => $quarterStart->subDays(7)->setTime(9, 0),
                    'expires_at' => $shelfLifeMonths === null
                        ? null
                        : $quarterStart->addMonths($shelfLifeMonths)->endOfMonth(),
                    'qty_received' => $quantity,
                    'qty_remaining' => 0,
                ],
            );

            StockMovement::query()->updateOrCreate(
                [
                    'movement_type' => 'receive',
                    'product_id' => $product->id,
                    'stock_lot_id' => $lot->id,
                    'notes' => "Quarterly replenishment delivery {$referenceNumber}",
                ],
                [
                    'asset_id' => null,
                    'requisition_id' => null,
                    'qty_delta' => $quantity,
                    'qty_before' => 0,
                    'qty_after' => $quantity,
                    'performed_by' => $performedBy->id,
                    'accountable_position_id' => $performedBy->position_id,
                    'ip_address' => '10.250.8.21',
                    'performed_at' => $quarterStart->subDays(7)->setTime(9, 0),
                ],
            );
        }

        StockMovement::query()
            ->where('product_id', $product->id)
            ->where('movement_type', 'issue')
            ->where('notes', 'like', $issueNotePrefix.'%')
            ->whereNotIn('notes', array_column($events, 'notes'))
            ->delete();

        $staleLotIds = StockLot::query()
            ->where('product_id', $product->id)
            ->where('reference_no', 'like', "DR-%-{$referenceCode}")
            ->whereNotIn('reference_no', $deliveryReferences)
            ->pluck('id');

        if ($staleLotIds->isNotEmpty()) {
            StockMovement::query()->whereIn('stock_lot_id', $staleLotIds)->delete();
            StockLot::query()->whereKey($staleLotIds)->delete();
        }

        return $events;
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

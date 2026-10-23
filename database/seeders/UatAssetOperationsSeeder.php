<?php

namespace Database\Seeders;

use App\Enums\ApprovalStatus;
use App\Enums\AssetConditionStatus;
use App\Enums\AssetLifecycleEventType;
use App\Enums\AssetLifecycleStatus;
use App\Enums\AssetStatus;
use App\Enums\AssetTransferStatus;
use App\Enums\InventoryCountReviewStatus;
use App\Enums\InventoryCountStatus;
use App\Enums\MaintenanceStatus;
use App\Enums\MaintenanceType;
use App\Enums\StockMovementReasonCode;
use App\Models\Asset;
use App\Models\AssetLifecycleEvent;
use App\Models\AssetMaintenance;
use App\Models\AssetTransfer;
use App\Models\Attachment;
use App\Models\Department;
use App\Models\InventoryCountLine;
use App\Models\InventoryCountSession;
use App\Models\Location;
use App\Models\Position;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

class UatAssetOperationsSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            throw new \RuntimeException('Operational seed data cannot be seeded in production.');
        }

        $timeline = CarbonImmutable::today()->setTime(13, 0);
        /** @var array<string, Department> $departments */
        $departments = Department::query()->get()->keyBy('code')->all();
        /** @var array<string, Position> $positions */
        $positions = Position::query()->get()->keyBy('code')->all();
        /** @var array<string, User> $users */
        $users = User::query()->get()->keyBy('email')->all();
        /** @var array<string, Asset> $assets */
        $assets = Asset::query()->get()->keyBy('tag_code')->all();
        /** @var array<string, Product> $products */
        $products = Product::query()->get()->keyBy('sku')->all();

        $locations = $this->seedLocations($departments);
        $this->assignAssetLocations($assets, $locations);
        $maintenances = $this->seedMaintenances($assets, $users, $timeline);
        $transfers = $this->seedTransfers($assets, $locations, $positions, $users, $timeline);
        $lifecycleEvents = $this->seedLifecycleEvents($assets, $users, $timeline);
        $countSessions = $this->seedInventoryCounts($products, $assets, $locations, $users, $timeline);

        $this->seedPrivateAttachment(
            $maintenances['projector-inspection'],
            $users['custodian@local.test'],
            'inspection',
            'Projector preventive maintenance report',
            'projector-preventive-maintenance-report.pdf',
            'Completed projector inspection: optics cleaned, lamp hours recorded, and display ports tested.',
        );
        $this->seedPrivateAttachment(
            $transfers['engineering-laptop'],
            $users['custodian@local.test'],
            'transfer',
            'Property transfer acknowledgment',
            'engineering-laptop-transfer-acknowledgment.pdf',
            'Transfer acknowledged by the College of Engineering property custodian.',
        );
        $this->seedPrivateAttachment(
            $lifecycleEvents['network-switch-disposal'],
            $users['custodian@local.test'],
            'disposal',
            'Information technology equipment disposal record',
            'network-switch-disposal-record.pdf',
            'Disposal approval record for obsolete network equipment with failed power and switching modules.',
        );
        $this->seedPrivateAttachment(
            $countSessions['completed'],
            $users['inventory.analyst@local.test'],
            'inspection',
            'Central stockroom physical count sheet',
            'central-stockroom-physical-count-sheet.pdf',
            'Signed physical count sheet for the completed central stockroom cycle count.',
        );
    }

    /**
     * @param  array<string, Department>  $departments
     * @return array<string, Location>
     */
    private function seedLocations(array $departments): array
    {
        $locations = [];

        foreach ([
            ['code' => 'SPMO-STOCKROOM', 'department' => 'SPMO', 'name' => 'Central Supply Stockroom', 'building' => 'Administration Building', 'room' => 'Ground Floor Stockroom', 'is_active' => true],
            ['code' => 'SPMO-PROP', 'department' => 'SPMO', 'name' => 'Property and Equipment Holding Area', 'building' => 'Administration Building', 'room' => 'Ground Floor Property Room', 'is_active' => true],
            ['code' => 'COE-COMPLAB', 'department' => 'COE', 'name' => 'Engineering Computer Laboratory', 'building' => 'Engineering and Science Building', 'room' => 'Room 304', 'is_active' => true],
            ['code' => 'COED-MEDIA', 'department' => 'COED', 'name' => 'Education Media Laboratory', 'building' => 'Academic Building', 'room' => 'Room 206', 'is_active' => true],
            ['code' => 'DIR-CONFERENCE', 'department' => 'DIR', 'name' => 'Director Conference Room', 'building' => 'Administration Building', 'room' => 'Room 201', 'is_active' => true],
            ['code' => 'IT-SERVER', 'department' => 'IT', 'name' => 'Network Operations and Server Room', 'building' => 'Information Technology Center', 'room' => 'Server Room 1', 'is_active' => true],
            ['code' => 'IT-WORKSHOP', 'department' => 'IT', 'name' => 'Information Technology Service Workshop', 'building' => 'Information Technology Center', 'room' => 'Workshop 1', 'is_active' => true],
            ['code' => 'LIB-SERVICE', 'department' => 'LIB', 'name' => 'Library Technical Services Area', 'building' => 'University Library', 'room' => 'Ground Floor', 'is_active' => true],
            ['code' => 'REG-RECORDS', 'department' => 'REG', 'name' => 'Registrar Records Processing Area', 'building' => 'Administration Building', 'room' => 'Room 105', 'is_active' => true],
            ['code' => 'STUDENT-ACTIVITY', 'department' => 'STUDENT', 'name' => 'Student Activity Center', 'building' => 'Student Services Building', 'room' => 'Multipurpose Hall', 'is_active' => true],
            ['code' => 'FMO-MECHANICAL', 'department' => 'FMO', 'name' => 'Facilities Mechanical Workshop', 'building' => 'Facilities Building', 'room' => 'Workshop A', 'is_active' => true],
            ['code' => 'BACC-FACULTY', 'department' => 'BACC', 'name' => 'Business and Accountancy Faculty Office', 'building' => 'Academic Building', 'room' => 'Room 401', 'is_active' => true],
            ['code' => 'ADMIN-RECORDS', 'department' => 'ADMIN', 'name' => 'Administrative Records Center', 'building' => 'Administration Building', 'room' => 'Basement Records Room', 'is_active' => true],
            ['code' => 'SPMO-OLD-STORAGE', 'department' => 'SPMO', 'name' => 'Former Property Storage Room', 'building' => 'Old Administration Annex', 'room' => 'Room 3', 'is_active' => false],
        ] as $definition) {
            $location = Location::query()->updateOrCreate(
                ['code' => $definition['code']],
                [
                    'department_id' => $departments[$definition['department']]->id,
                    'name' => $definition['name'],
                    'building' => $definition['building'],
                    'room' => $definition['room'],
                    'is_active' => $definition['is_active'],
                ],
            );

            $locations[$definition['code']] = $location;
        }

        return $locations;
    }

    /**
     * @param  array<string, Asset>  $assets
     * @param  array<string, Location>  $locations
     */
    private function assignAssetLocations(array $assets, array $locations): void
    {
        foreach ([
            'AST-COE-0001' => 'COE-COMPLAB',
            'AST-COED-0001' => 'COED-MEDIA',
            'AST-ADMIN-0002' => 'SPMO-PROP',
            'AST-DIR-0001' => 'DIR-CONFERENCE',
            'AST-STU-0001' => 'STUDENT-ACTIVITY',
            'AST-REG-0001' => 'REG-RECORDS',
            'AST-LIB-0001' => 'LIB-SERVICE',
            'AST-SPMO-0001' => 'SPMO-PROP',
            'AST-ADMIN-0001' => 'IT-WORKSHOP',
            'AST-BACC-0001' => 'BACC-FACULTY',
            'AST-REG-0002' => 'REG-RECORDS',
            'AST-IT-0001' => 'IT-SERVER',
            'AST-FMO-0001' => 'FMO-MECHANICAL',
            'AST-IT-0002' => 'SPMO-PROP',
        ] as $assetTag => $locationCode) {
            $assets[$assetTag]->forceFill(['current_location_id' => $locations[$locationCode]->id])->save();
        }
    }

    /**
     * @param  array<string, Asset>  $assets
     * @param  array<string, User>  $users
     * @return array<string, AssetMaintenance>
     */
    private function seedMaintenances(array $assets, array $users, CarbonImmutable $timeline): array
    {
        $definitions = [
            'air-conditioner-reported' => [
                'asset' => 'AST-FMO-0001', 'type' => MaintenanceType::Corrective, 'status' => MaintenanceStatus::Reported,
                'issue' => 'Indoor unit is leaking water and no longer cools the room consistently.',
                'description' => 'Inspect the drainage line, evaporator coil, refrigerant pressure, and electrical controls.',
                'reported_by' => 'facilities.custodian@local.test', 'reported_date' => $timeline->subDay(),
                'assigned_to' => null, 'provider' => null, 'scheduled_for' => null, 'completed_at' => null,
                'cost' => null, 'findings' => null, 'notes' => 'Awaiting technical assessment and service quotation.',
            ],
            'network-switch-scheduled' => [
                'asset' => 'AST-IT-0001', 'type' => MaintenanceType::Preventive, 'status' => MaintenanceStatus::Scheduled,
                'issue' => 'Quarterly preventive maintenance is due for the core access switch.',
                'description' => 'Back up configuration, inspect ports, clean ventilation openings, and test redundant uplinks.',
                'reported_by' => 'it.expert@local.test', 'reported_date' => $timeline->subDays(4),
                'assigned_to' => 'it.expert@local.test', 'provider' => 'University Information Technology Office', 'scheduled_for' => $timeline->addDays(3), 'completed_at' => null,
                'cost' => null, 'findings' => null, 'notes' => 'Maintenance window coordinated with offices using the affected network segment.',
            ],
            'tablet-repair' => [
                'asset' => 'AST-ADMIN-0001', 'type' => MaintenanceType::Repair, 'status' => MaintenanceStatus::InProgress,
                'issue' => 'Tablet does not hold a charge and restarts when disconnected from power.',
                'description' => 'Replace the battery assembly and complete a charging-cycle diagnostic.',
                'reported_by' => 'admin.office.custodian@local.test', 'reported_date' => $timeline->subDays(7),
                'assigned_to' => 'it.expert@local.test', 'provider' => 'Northstar Digital Systems Corporation', 'scheduled_for' => $timeline->subDays(2), 'completed_at' => null,
                'cost' => 3850.00, 'findings' => 'Battery health measured below service threshold; replacement is in progress.', 'notes' => 'Asset remains unavailable until the post-repair test is completed.',
            ],
            'projector-inspection' => [
                'asset' => 'AST-DIR-0001', 'type' => MaintenanceType::Inspection, 'status' => MaintenanceStatus::Completed,
                'issue' => 'Annual audiovisual equipment inspection was due.',
                'description' => 'Inspect lamp hours, image quality, filters, ports, remote control, and power cable.',
                'reported_by' => 'director.office.custodian@local.test', 'reported_date' => $timeline->subDays(18),
                'assigned_to' => 'it.expert@local.test', 'provider' => 'University Information Technology Office', 'scheduled_for' => $timeline->subDays(12), 'completed_at' => $timeline->subDays(12)->addHours(2),
                'cost' => 0.00, 'findings' => 'Equipment passed the functional inspection after filter cleaning and cable replacement.', 'notes' => 'Next preventive inspection is due in twelve months.',
            ],
            'cabinet-cancelled' => [
                'asset' => 'AST-BACC-0001', 'type' => MaintenanceType::Other, 'status' => MaintenanceStatus::Cancelled,
                'issue' => 'A replacement key was requested for the filing cabinet.',
                'description' => 'Verify the lock code and arrange key duplication if no spare key is available.',
                'reported_by' => 'business.custodian@local.test', 'reported_date' => $timeline->subDays(9),
                'assigned_to' => 'facilities.custodian@local.test', 'provider' => null, 'scheduled_for' => $timeline->subDays(5), 'completed_at' => null,
                'cost' => null, 'findings' => 'The labeled spare key was located during the records-room inventory.', 'notes' => 'Service request cancelled because no repair was required.',
            ],
        ];

        $maintenances = [];
        foreach ($definitions as $key => $definition) {
            $maintenances[$key] = AssetMaintenance::query()->updateOrCreate(
                ['asset_id' => $assets[$definition['asset']]->id, 'reported_issue' => $definition['issue']],
                [
                    'maintenance_type' => $definition['type'], 'status' => $definition['status'],
                    'description' => $definition['description'], 'reported_by' => $users[$definition['reported_by']]->id,
                    'reported_date' => $definition['reported_date'],
                    'assigned_to' => $definition['assigned_to'] === null ? null : $users[$definition['assigned_to']]->id,
                    'service_provider' => $definition['provider'], 'scheduled_for' => $definition['scheduled_for'],
                    'completed_at' => $definition['completed_at'], 'repair_cost' => $definition['cost'],
                    'findings' => $definition['findings'], 'notes' => $definition['notes'],
                ],
            );
        }

        $assets['AST-FMO-0001']->forceFill([
            'status' => AssetStatus::Unserviceable,
            'condition_status' => AssetConditionStatus::Damaged,
        ])->save();

        return $maintenances;
    }

    /**
     * @param  array<string, Asset>  $assets
     * @param  array<string, Location>  $locations
     * @param  array<string, Position>  $positions
     * @param  array<string, User>  $users
     * @return array<string, AssetTransfer>
     */
    private function seedTransfers(array $assets, array $locations, array $positions, array $users, CarbonImmutable $timeline): array
    {
        $definitions = [
            'engineering-laptop' => [
                'asset' => 'AST-COE-0001', 'from' => 'SPMO-PROP', 'to' => 'COE-COMPLAB',
                'from_position' => 'POS-PC-CHIEF', 'to_position' => 'POS-COE-PC', 'status' => AssetTransferStatus::Approved,
                'reason' => 'Permanent deployment for engineering laboratory instruction and faculty research.',
                'date' => $timeline->subDays(45), 'initiated_by' => 'custodian@local.test', 'approved_by' => 'supply@local.test',
                'approved_at' => $timeline->subDays(44), 'cancelled_by' => null, 'cancelled_at' => null,
                'notes' => 'Asset tag and accessories were verified during acknowledgment.',
            ],
            'registrar-scanner' => [
                'asset' => 'AST-REG-0002', 'from' => 'REG-RECORDS', 'to' => 'ADMIN-RECORDS',
                'from_position' => 'POS-REG-PC', 'to_position' => 'POS-ADMIN-PC', 'status' => AssetTransferStatus::Pending,
                'reason' => 'Temporary deployment for the university-wide records digitization project.',
                'date' => $timeline->addDays(2), 'initiated_by' => 'registrar.custodian@local.test', 'approved_by' => null,
                'approved_at' => null, 'cancelled_by' => null, 'cancelled_at' => null,
                'notes' => 'Pending property office review and receiving-office acknowledgment.',
            ],
            'director-projector-rejected' => [
                'asset' => 'AST-DIR-0001', 'from' => 'DIR-CONFERENCE', 'to' => 'LIB-SERVICE',
                'from_position' => 'POS-DIR-PC', 'to_position' => 'POS-LIB-PC', 'status' => AssetTransferStatus::Rejected,
                'reason' => 'Requested reassignment to support library audiovisual activities.',
                'date' => $timeline->subDays(6), 'initiated_by' => 'library.custodian@local.test', 'approved_by' => 'supply@local.test',
                'approved_at' => $timeline->subDays(5), 'cancelled_by' => null, 'cancelled_at' => null,
                'notes' => 'Request declined because the projector is assigned to scheduled executive meetings.',
            ],
            'student-projector-cancelled' => [
                'asset' => 'AST-STU-0001', 'from' => 'STUDENT-ACTIVITY', 'to' => 'COE-COMPLAB',
                'from_position' => 'POS-STUDENT-PC', 'to_position' => 'POS-COE-PC', 'status' => AssetTransferStatus::Cancelled,
                'reason' => 'Temporary relocation for an engineering extension program.',
                'date' => $timeline->subDays(3), 'initiated_by' => 'eng.custodian@local.test', 'approved_by' => null,
                'approved_at' => null, 'cancelled_by' => 'eng.custodian@local.test', 'cancelled_at' => $timeline->subDays(2),
                'notes' => 'Program venue changed before property office approval.',
            ],
        ];

        $transfers = [];
        foreach ($definitions as $key => $definition) {
            $transfers[$key] = AssetTransfer::query()->updateOrCreate(
                ['asset_id' => $assets[$definition['asset']]->id, 'reason' => $definition['reason']],
                [
                    'from_location_id' => $locations[$definition['from']]->id,
                    'to_location_id' => $locations[$definition['to']]->id,
                    'from_position_id' => $positions[$definition['from_position']]->id,
                    'to_position_id' => $positions[$definition['to_position']]->id,
                    'status' => $definition['status'], 'transfer_date' => $definition['date'],
                    'initiated_by' => $users[$definition['initiated_by']]->id,
                    'approved_by' => $definition['approved_by'] === null ? null : $users[$definition['approved_by']]->id,
                    'approved_at' => $definition['approved_at'],
                    'cancelled_by' => $definition['cancelled_by'] === null ? null : $users[$definition['cancelled_by']]->id,
                    'cancelled_at' => $definition['cancelled_at'], 'notes' => $definition['notes'],
                ],
            );
        }

        return $transfers;
    }

    /**
     * @param  array<string, Asset>  $assets
     * @param  array<string, User>  $users
     * @return array<string, AssetLifecycleEvent>
     */
    private function seedLifecycleEvents(array $assets, array $users, CarbonImmutable $timeline): array
    {
        $definitions = [
            'scanner-acquisition' => [
                'asset' => 'AST-REG-0002', 'type' => AssetLifecycleEventType::Acquisition, 'status' => ApprovalStatus::Approved,
                'from_lifecycle' => null, 'to_lifecycle' => AssetLifecycleStatus::Active,
                'from_condition' => null, 'to_condition' => AssetConditionStatus::Serviceable,
                'reason' => 'Equipment accepted after delivery inspection and property tagging.', 'method' => 'Public procurement',
                'reference' => 'IAR-2026-00418', 'proposed_for' => $timeline->subDays(90), 'occurred_at' => $timeline->subDays(89),
                'initiated_by' => 'custodian@local.test', 'approved_by' => 'supply@local.test', 'approved_at' => $timeline->subDays(89),
                'notes' => 'Acceptance and inspection report completed with no exceptions.',
            ],
            'air-conditioner-condition' => [
                'asset' => 'AST-FMO-0001', 'type' => AssetLifecycleEventType::ConditionChange, 'status' => ApprovalStatus::Approved,
                'from_lifecycle' => AssetLifecycleStatus::Active, 'to_lifecycle' => AssetLifecycleStatus::Active,
                'from_condition' => AssetConditionStatus::Serviceable, 'to_condition' => AssetConditionStatus::Damaged,
                'reason' => 'Water leakage and inconsistent cooling require corrective maintenance.', 'method' => 'Facilities inspection',
                'reference' => 'FMO-INS-2026-0037', 'proposed_for' => $timeline->subDay(), 'occurred_at' => $timeline->subDay(),
                'initiated_by' => 'facilities.custodian@local.test', 'approved_by' => 'custodian@local.test', 'approved_at' => $timeline->subHours(18),
                'notes' => 'Asset remains active in the register but unavailable for operational use.',
            ],
            'laptop-retirement' => [
                'asset' => 'AST-ADMIN-0002', 'type' => AssetLifecycleEventType::Retirement, 'status' => ApprovalStatus::Approved,
                'from_lifecycle' => AssetLifecycleStatus::Active, 'to_lifecycle' => AssetLifecycleStatus::Retired,
                'from_condition' => AssetConditionStatus::Damaged, 'to_condition' => AssetConditionStatus::Unserviceable,
                'reason' => 'Mainboard failure and discontinued replacement parts make repair uneconomical.', 'method' => 'Beyond economical repair',
                'reference' => 'PAR-2026-00074', 'proposed_for' => $timeline->subDays(20), 'occurred_at' => $timeline->subDays(15),
                'initiated_by' => 'custodian@local.test', 'approved_by' => 'supply@local.test', 'approved_at' => $timeline->subDays(15),
                'notes' => 'Asset secured in the property holding area pending authorized disposal.',
            ],
            'network-switch-disposal' => [
                'asset' => 'AST-IT-0002', 'type' => AssetLifecycleEventType::Disposal, 'status' => ApprovalStatus::Approved,
                'from_lifecycle' => AssetLifecycleStatus::Retired, 'to_lifecycle' => AssetLifecycleStatus::Disposed,
                'from_condition' => AssetConditionStatus::Unserviceable, 'to_condition' => AssetConditionStatus::Unserviceable,
                'reason' => 'Obsolete equipment failed electrical and functional inspection after retirement.', 'method' => 'Authorized electronic waste recycler',
                'reference' => 'DISP-2026-00019', 'proposed_for' => $timeline->subDays(35), 'occurred_at' => $timeline->subDays(28),
                'initiated_by' => 'it.expert@local.test', 'approved_by' => 'admin@local.test', 'approved_at' => $timeline->subDays(29),
                'notes' => 'Storage media was not present; equipment identification plate was recorded before release.',
            ],
            'cabinet-retirement-pending' => [
                'asset' => 'AST-BACC-0001', 'type' => AssetLifecycleEventType::Retirement, 'status' => ApprovalStatus::Pending,
                'from_lifecycle' => AssetLifecycleStatus::Active, 'to_lifecycle' => AssetLifecycleStatus::Retired,
                'from_condition' => AssetConditionStatus::Serviceable, 'to_condition' => AssetConditionStatus::Unserviceable,
                'reason' => 'Drawer rails and locking mechanism require evaluation before further use.', 'method' => 'Condition assessment',
                'reference' => 'PAR-2026-00103', 'proposed_for' => $timeline->addDays(5), 'occurred_at' => null,
                'initiated_by' => 'business.custodian@local.test', 'approved_by' => null, 'approved_at' => null,
                'notes' => 'Pending physical inspection by the property office.',
            ],
            'printer-disposal-rejected' => [
                'asset' => 'AST-LIB-0001', 'type' => AssetLifecycleEventType::Disposal, 'status' => ApprovalStatus::Rejected,
                'from_lifecycle' => AssetLifecycleStatus::Active, 'to_lifecycle' => AssetLifecycleStatus::Disposed,
                'from_condition' => AssetConditionStatus::Damaged, 'to_condition' => AssetConditionStatus::Unserviceable,
                'reason' => 'Repeated paper-feed errors led to a disposal request.', 'method' => 'Property disposal',
                'reference' => 'DISP-2026-00022', 'proposed_for' => $timeline->subDays(8), 'occurred_at' => null,
                'initiated_by' => 'library.custodian@local.test', 'approved_by' => 'supply@local.test', 'approved_at' => $timeline->subDays(6),
                'notes' => 'Request rejected because the inspection found the pickup roller repairable.',
            ],
        ];

        $events = [];
        foreach ($definitions as $key => $definition) {
            $events[$key] = AssetLifecycleEvent::query()->updateOrCreate(
                [
                    'asset_id' => $assets[$definition['asset']]->id,
                    'event_type' => $definition['type'],
                    'supporting_reference' => $definition['reference'],
                ],
                [
                    'status' => $definition['status'], 'from_lifecycle_status' => $definition['from_lifecycle'],
                    'to_lifecycle_status' => $definition['to_lifecycle'], 'from_condition_status' => $definition['from_condition'],
                    'to_condition_status' => $definition['to_condition'], 'reason' => $definition['reason'],
                    'method' => $definition['method'], 'proposed_for' => $definition['proposed_for'],
                    'occurred_at' => $definition['occurred_at'], 'initiated_by' => $users[$definition['initiated_by']]->id,
                    'approved_by' => $definition['approved_by'] === null ? null : $users[$definition['approved_by']]->id,
                    'approved_at' => $definition['approved_at'], 'notes' => $definition['notes'],
                ],
            );
        }

        $assets['AST-ADMIN-0002']->forceFill([
            'status' => AssetStatus::Condemned,
            'lifecycle_status' => AssetLifecycleStatus::Retired,
            'condition_status' => AssetConditionStatus::Unserviceable,
        ])->save();
        $assets['AST-IT-0002']->forceFill([
            'status' => AssetStatus::Condemned,
            'lifecycle_status' => AssetLifecycleStatus::Disposed,
            'condition_status' => AssetConditionStatus::Unserviceable,
        ])->save();

        return $events;
    }

    /**
     * @param  array<string, Product>  $products
     * @param  array<string, Asset>  $assets
     * @param  array<string, Location>  $locations
     * @param  array<string, User>  $users
     * @return array<string, InventoryCountSession>
     */
    private function seedInventoryCounts(array $products, array $assets, array $locations, array $users, CarbonImmutable $timeline): array
    {
        $movementCutoffId = (int) (StockMovement::query()->max('id') ?? 0);
        $sessions = [];

        $sessions['completed'] = $this->syncCountSession('PHY-2026-0001', $locations['SPMO-STOCKROOM'], $users, [
            'assigned_to' => 'inventory.analyst@local.test', 'approved_by' => 'supply@local.test',
            'counting_date' => $timeline->subDays(14), 'scope' => 'Central stockroom consumables - quarterly cycle count',
            'status' => InventoryCountStatus::Completed, 'snapshot_at' => $timeline->subDays(14)->setTime(8, 0),
            'movement_cutoff_id' => $movementCutoffId, 'submitted_at' => $timeline->subDays(14)->setTime(11, 30),
            'approved_at' => $timeline->subDays(13)->setTime(9, 0), 'completed_at' => $timeline->subDays(13)->setTime(9, 15),
            'cancelled_at' => null, 'remarks' => 'Quarterly count completed with one approved toner variance.',
        ]);

        $toner = $products['CON-TONER-151A'];
        $tonerLot = $toner->lots()->where('reference_no', 'DR-2026-03041')->firstOrFail();
        $adjustmentMovement = StockMovement::query()->updateOrCreate(
            [
                'movement_type' => 'adjustment', 'product_id' => $toner->id,
                'stock_lot_id' => $tonerLot->id, 'notes' => 'Approved toner variance from physical count PHY-2026-0001.',
            ],
            [
                'reason_code' => StockMovementReasonCode::Shrinkage, 'asset_id' => null, 'requisition_id' => null,
                'qty_delta' => -2, 'qty_before' => 12, 'qty_after' => 10, 'counted_qty' => 10, 'variance_qty' => -2,
                'performed_by' => $users['supply@local.test']->id,
                'accountable_position_id' => $users['supply@local.test']->position_id,
                'ip_address' => '10.250.12.15', 'performed_at' => $timeline->subDays(13)->setTime(9, 15),
            ],
        );
        $tonerLot->forceFill(['qty_remaining' => 10])->save();
        $toner->stock()->updateOrCreate([], ['on_hand_qty' => 10]);

        InventoryCountLine::query()->updateOrCreate(
            ['inventory_count_session_id' => $sessions['completed']->id, 'stock_lot_id' => $tonerLot->id],
            [
                'product_id' => $toner->id, 'asset_id' => null, 'expected_qty_snapshot' => 12,
                'counted_qty' => 10, 'expected_qty_at_count' => 12, 'variance_qty' => -2,
                'counted_by' => $users['inventory.analyst@local.test']->id,
                'counted_at' => $timeline->subDays(14)->setTime(10, 45),
                'review_status' => InventoryCountReviewStatus::Approved,
                'count_notes' => 'Two cartridges were not located after shelf and receiving-area recount.',
                'review_notes' => 'Variance approved after checking receiving records and issue logs.',
                'reviewed_by' => $users['supply@local.test']->id,
                'reviewed_at' => $timeline->subDays(13)->setTime(9, 0),
                'adjustment_movement_id' => $adjustmentMovement->id,
            ],
        );

        $sessions['submitted'] = $this->syncCountSession('PHY-2026-0002', $locations['SPMO-STOCKROOM'], $users, [
            'assigned_to' => 'inventory.analyst@local.test', 'approved_by' => null,
            'counting_date' => $timeline->subDay(), 'scope' => 'Protective equipment shelf verification',
            'status' => InventoryCountStatus::Submitted, 'snapshot_at' => $timeline->subDay()->setTime(8, 30),
            'movement_cutoff_id' => (int) (StockMovement::query()->max('id') ?? 0),
            'submitted_at' => $timeline->subDay()->setTime(11, 0), 'approved_at' => null,
            'completed_at' => null, 'cancelled_at' => null,
            'remarks' => 'Submitted for discrepancy review and recount decision.',
        ]);
        $gloves = $products['CON-GLOVES-M'];
        $glovesLot = $gloves->lots()->where('reference_no', 'DR-2026-03018')->firstOrFail();
        InventoryCountLine::query()->updateOrCreate(
            ['inventory_count_session_id' => $sessions['submitted']->id, 'stock_lot_id' => $glovesLot->id],
            [
                'product_id' => $gloves->id, 'asset_id' => null, 'expected_qty_snapshot' => 6,
                'counted_qty' => 5, 'expected_qty_at_count' => 6, 'variance_qty' => -1,
                'counted_by' => $users['inventory.analyst@local.test']->id,
                'counted_at' => $timeline->subDay()->setTime(10, 30),
                'review_status' => InventoryCountReviewStatus::Pending,
                'count_notes' => 'One box is under review against the clinic issue log.',
                'review_notes' => null, 'reviewed_by' => null, 'reviewed_at' => null, 'adjustment_movement_id' => null,
            ],
        );
        StockMovement::query()->updateOrCreate(
            [
                'movement_type' => 'cycle_count', 'product_id' => $gloves->id,
                'stock_lot_id' => $glovesLot->id,
                'notes' => 'Physical count recorded for session PHY-2026-0002; no balance adjustment posted.',
            ],
            [
                'reason_code' => StockMovementReasonCode::RoutineCount, 'asset_id' => null, 'requisition_id' => null,
                'qty_delta' => 0, 'qty_before' => 6, 'qty_after' => 6, 'counted_qty' => 5, 'variance_qty' => -1,
                'performed_by' => $users['inventory.analyst@local.test']->id,
                'accountable_position_id' => $users['inventory.analyst@local.test']->position_id,
                'ip_address' => '10.250.12.16', 'performed_at' => $timeline->subDay()->setTime(10, 30),
            ],
        );

        $sessions['in-progress'] = $this->syncCountSession('PHY-2026-0003', $locations['IT-SERVER'], $users, [
            'assigned_to' => 'it.expert@local.test', 'approved_by' => null,
            'counting_date' => $timeline, 'scope' => 'Information technology network assets',
            'status' => InventoryCountStatus::InProgress, 'snapshot_at' => $timeline->setTime(8, 0),
            'movement_cutoff_id' => (int) (StockMovement::query()->max('id') ?? 0),
            'submitted_at' => null, 'approved_at' => null, 'completed_at' => null, 'cancelled_at' => null,
            'remarks' => 'Network equipment count is in progress.',
        ]);
        InventoryCountLine::query()->updateOrCreate(
            ['inventory_count_session_id' => $sessions['in-progress']->id, 'asset_id' => $assets['AST-IT-0001']->id],
            [
                'product_id' => $assets['AST-IT-0001']->product_id, 'stock_lot_id' => null,
                'expected_qty_snapshot' => 1, 'counted_qty' => 1, 'expected_qty_at_count' => 1, 'variance_qty' => 0,
                'counted_by' => $users['it.expert@local.test']->id, 'counted_at' => $timeline->setTime(10, 0),
                'review_status' => InventoryCountReviewStatus::Pending,
                'count_notes' => 'Asset tag and rack position verified.', 'review_notes' => null,
                'reviewed_by' => null, 'reviewed_at' => null, 'adjustment_movement_id' => null,
            ],
        );

        $sessions['draft'] = $this->syncCountSession('PHY-2026-0004', $locations['REG-RECORDS'], $users, [
            'assigned_to' => 'registrar.custodian@local.test', 'approved_by' => null,
            'counting_date' => $timeline->addDays(4), 'scope' => 'Registrar office equipment',
            'status' => InventoryCountStatus::Draft, 'snapshot_at' => $timeline,
            'movement_cutoff_id' => (int) (StockMovement::query()->max('id') ?? 0),
            'submitted_at' => null, 'approved_at' => null, 'completed_at' => null, 'cancelled_at' => null,
            'remarks' => 'Prepared for the scheduled monthly equipment verification.',
        ]);
        InventoryCountLine::query()->updateOrCreate(
            ['inventory_count_session_id' => $sessions['draft']->id, 'asset_id' => $assets['AST-REG-0002']->id],
            [
                'product_id' => $assets['AST-REG-0002']->product_id, 'stock_lot_id' => null,
                'expected_qty_snapshot' => 1, 'counted_qty' => null, 'expected_qty_at_count' => null, 'variance_qty' => null,
                'counted_by' => null, 'counted_at' => null, 'review_status' => InventoryCountReviewStatus::Pending,
                'count_notes' => null, 'review_notes' => null, 'reviewed_by' => null,
                'reviewed_at' => null, 'adjustment_movement_id' => null,
            ],
        );

        $sessions['cancelled'] = $this->syncCountSession('PHY-2026-0005', $locations['SPMO-OLD-STORAGE'], $users, [
            'assigned_to' => 'inventory.analyst@local.test', 'approved_by' => null,
            'counting_date' => $timeline->subDays(30), 'scope' => 'Former property storage room closure count',
            'status' => InventoryCountStatus::Cancelled, 'snapshot_at' => $timeline->subDays(31),
            'movement_cutoff_id' => $movementCutoffId, 'submitted_at' => null, 'approved_at' => null,
            'completed_at' => null, 'cancelled_at' => $timeline->subDays(30)->setTime(9, 15),
            'remarks' => 'Cancelled because all accountable assets had already been transferred to the central holding area.',
        ]);

        return $sessions;
    }

    /**
     * @param  array<string, User>  $users
     * @param  array{
     *     assigned_to: string,
     *     approved_by: string|null,
     *     counting_date: CarbonImmutable,
     *     scope: string,
     *     status: InventoryCountStatus,
     *     snapshot_at: CarbonImmutable,
     *     movement_cutoff_id: int,
     *     submitted_at: CarbonImmutable|null,
     *     approved_at: CarbonImmutable|null,
     *     completed_at: CarbonImmutable|null,
     *     cancelled_at: CarbonImmutable|null,
     *     remarks: string
     * }  $definition
     */
    private function syncCountSession(string $referenceNumber, Location $location, array $users, array $definition): InventoryCountSession
    {
        return InventoryCountSession::query()->updateOrCreate(
            ['reference_no' => $referenceNumber],
            [
                'location_id' => $location->id,
                'created_by' => $users['inventory.analyst@local.test']->id,
                'assigned_to' => $users[$definition['assigned_to']]->id,
                'approved_by' => $definition['approved_by'] === null ? null : $users[$definition['approved_by']]->id,
                'counting_date' => $definition['counting_date'], 'scope' => $definition['scope'],
                'status' => $definition['status'], 'snapshot_at' => $definition['snapshot_at'],
                'movement_cutoff_id' => $definition['movement_cutoff_id'],
                'submitted_at' => $definition['submitted_at'], 'approved_at' => $definition['approved_at'],
                'completed_at' => $definition['completed_at'], 'cancelled_at' => $definition['cancelled_at'],
                'remarks' => $definition['remarks'],
            ],
        );
    }

    private function seedPrivateAttachment(
        AssetMaintenance|AssetTransfer|AssetLifecycleEvent|InventoryCountSession $attachable,
        User $uploadedBy,
        string $category,
        string $title,
        string $originalName,
        string $body,
    ): void {
        $contents = "%PDF-1.4\n% PUP-PRISM operational document\n{$body}\n%%EOF\n";
        $checksum = hash('sha256', $contents);
        $storedName = $checksum.'.pdf';
        $path = 'attachments/operational-records/'.$storedName;

        if (! Storage::disk('local')->put($path, $contents)) {
            throw new \RuntimeException("Unable to write private seed attachment [{$originalName}].");
        }

        $attachment = Attachment::withTrashed()->firstOrNew([
            'attachable_type' => $attachable::class,
            'attachable_id' => $attachable->id,
            'checksum' => $checksum,
        ]);

        $attachment->fill([
            'uploaded_by' => $uploadedBy->id, 'title' => $title, 'category' => $category,
            'description' => $body, 'disk' => 'local', 'path' => $path,
            'original_name' => $originalName, 'stored_name' => $storedName,
            'mime_type' => 'application/pdf', 'size' => strlen($contents),
        ]);
        $attachment->deleted_at = null;
        $attachment->save();
    }
}

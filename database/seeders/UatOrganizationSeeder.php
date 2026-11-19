<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Position;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class UatOrganizationSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            throw new \RuntimeException('Operational seed data cannot be seeded in production.');
        }

        foreach (['Admin', 'Supply Head', 'Property Custodian'] as $roleName) {
            Role::findOrCreate($roleName);
        }

        $departments = [];

        foreach ([
            ['code' => 'ADMIN', 'name' => 'Administration Office'],
            ['code' => 'ACCT', 'name' => 'Accounting Office'],
            ['code' => 'BACC', 'name' => 'College of Business and Accountancy'],
            ['code' => 'CLINIC', 'name' => 'University Health Services'],
            ['code' => 'COED', 'name' => 'College of Education'],
            ['code' => 'COE', 'name' => 'College of Engineering'],
            ['code' => 'DIR', 'name' => 'Office of the Director'],
            ['code' => 'FMO', 'name' => 'Facilities Management Office'],
            ['code' => 'IT', 'name' => 'Information Technology Office'],
            ['code' => 'LIB', 'name' => 'University Library'],
            ['code' => 'REG', 'name' => 'Registrar Office'],
            ['code' => 'SPMO', 'name' => 'Supply and Property Management Office'],
            ['code' => 'STUDENT', 'name' => 'Office of Student Affairs'],
        ] as $department) {
            $departments[$department['code']] = Department::query()->updateOrCreate(
                ['code' => $department['code']],
                ['name' => $department['name'], 'is_active' => true],
            );
        }

        foreach ([
            ['code' => 'POS-ADMIN-SYS', 'department' => 'ADMIN', 'title' => 'System Administrator & Operations Manager'],
            ['code' => 'POS-IT-DIR', 'department' => 'IT', 'title' => 'IT Director & Inventory Lead'],
            ['code' => 'POS-WH-SUP', 'department' => 'SPMO', 'title' => 'Warehouse Supervisor'],
            ['code' => 'POS-PROC-OFF', 'department' => 'SPMO', 'title' => 'Procurement Officer'],
            ['code' => 'POS-LOG-COORD', 'department' => 'FMO', 'title' => 'Logistics Coordinator'],
            ['code' => 'POS-ACCT-PC', 'department' => 'ACCT', 'title' => 'Property Custodian - Accounting'],
            ['code' => 'POS-SUP-HEAD', 'department' => 'SPMO', 'title' => 'Supply Head'],
            ['code' => 'POS-PC-CHIEF', 'department' => 'SPMO', 'title' => 'Chief Property Custodian'],
            ['code' => 'POS-DIRECTOR', 'department' => 'DIR', 'title' => 'Director'],
            ['code' => 'POS-IT-EXPERT', 'department' => 'IT', 'title' => 'IT Expert'],
            ['code' => 'POS-CLINIC-PC', 'department' => 'CLINIC', 'title' => 'Property Custodian - Health Services'],
            ['code' => 'POS-COE-PC', 'department' => 'COE', 'title' => 'Property Custodian - Engineering'],
            ['code' => 'POS-COED-PC', 'department' => 'COED', 'title' => 'Property Custodian - Education'],
            ['code' => 'POS-BACC-PC', 'department' => 'BACC', 'title' => 'Property Custodian - Business and Accountancy'],
            ['code' => 'POS-REG-PC', 'department' => 'REG', 'title' => 'Property Custodian - Registrar'],
            ['code' => 'POS-LIB-PC', 'department' => 'LIB', 'title' => 'Property Custodian - Library'],
            ['code' => 'POS-STUDENT-PC', 'department' => 'STUDENT', 'title' => 'Property Custodian - Student Affairs'],
            ['code' => 'POS-ADMIN-PC', 'department' => 'ADMIN', 'title' => 'Property Custodian - Administration'],
            ['code' => 'POS-DIR-PC', 'department' => 'DIR', 'title' => 'Property Custodian - Director Office'],
            ['code' => 'POS-FMO-PC', 'department' => 'FMO', 'title' => 'Property Custodian - Facilities Management'],
            ['code' => 'POS-SPMO-ANL', 'department' => 'SPMO', 'title' => 'Inventory Analyst'],
        ] as $position) {
            $this->seedPosition(
                code: $position['code'],
                department: $departments[$position['department']],
                title: $position['title'],
            );
        }

        $this->call(UserSeeder::class);
    }

    protected function seedPosition(string $code, Department $department, string $title): Position
    {
        $existingByDepartmentAndTitle = Position::query()
            ->where('department_id', $department->id)
            ->where('title', $title)
            ->first();

        $existingByCode = Position::query()
            ->where('code', $code)
            ->first();

        if (
            $existingByDepartmentAndTitle instanceof Position
            && $existingByCode instanceof Position
            && ! $existingByDepartmentAndTitle->is($existingByCode)
        ) {
            throw new \RuntimeException(sprintf(
                'Unable to seed position [%s]. Existing code [%s] and department/title [%s / %s] point to different rows.',
                $title,
                $code,
                $department->code ?? $department->id,
                $title,
            ));
        }

        $position = $existingByDepartmentAndTitle ?? $existingByCode ?? new Position;

        $position->fill([
            'code' => $code,
            'department_id' => $department->id,
            'title' => $title,
            'is_active' => true,
        ]);

        $position->save();

        return $position;
    }
}

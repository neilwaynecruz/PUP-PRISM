<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Position;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class UatOrganizationSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['Admin', 'Supply Head', 'Property Custodian'] as $roleName) {
            Role::findOrCreate($roleName);
        }

        $departments = [];

        foreach ([
            ['code' => 'ADMIN', 'name' => 'Administration Office'],
            ['code' => 'BACC', 'name' => 'College of Business and Accountancy'],
            ['code' => 'COED', 'name' => 'College of Education'],
            ['code' => 'COE', 'name' => 'College of Engineering'],
            ['code' => 'DIR', 'name' => 'Office of the Director'],
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

        $positions = [];

        foreach ([
            ['code' => 'POS-ADMIN-SYS', 'department' => 'IT', 'title' => 'PRISM System Administrator'],
            ['code' => 'POS-SUP-HEAD', 'department' => 'SPMO', 'title' => 'Supply Head'],
            ['code' => 'POS-PC-CHIEF', 'department' => 'SPMO', 'title' => 'Chief Property Custodian'],
            ['code' => 'POS-DIRECTOR', 'department' => 'DIR', 'title' => 'Director'],
            ['code' => 'POS-IT-EXPERT', 'department' => 'IT', 'title' => 'IT Expert'],
            ['code' => 'POS-COE-PC', 'department' => 'COE', 'title' => 'Property Custodian - Engineering'],
            ['code' => 'POS-COED-PC', 'department' => 'COED', 'title' => 'Property Custodian - Education'],
            ['code' => 'POS-BACC-PC', 'department' => 'BACC', 'title' => 'Property Custodian - Business and Accountancy'],
            ['code' => 'POS-REG-PC', 'department' => 'REG', 'title' => 'Property Custodian - Registrar'],
            ['code' => 'POS-LIB-PC', 'department' => 'LIB', 'title' => 'Property Custodian - Library'],
            ['code' => 'POS-STUDENT-PC', 'department' => 'STUDENT', 'title' => 'Property Custodian - Student Affairs'],
            ['code' => 'POS-ADMIN-PC', 'department' => 'ADMIN', 'title' => 'Property Custodian - Administration'],
            ['code' => 'POS-DIR-PC', 'department' => 'DIR', 'title' => 'Property Custodian - Director Office'],
            ['code' => 'POS-SPMO-ANL', 'department' => 'SPMO', 'title' => 'Inventory Analyst'],
        ] as $position) {
            $positions[$position['code']] = $this->seedPosition(
                code: $position['code'],
                department: $departments[$position['department']],
                title: $position['title'],
            );
        }

        $this->seedUser('admin@local.test', 'Admin User', $positions['POS-ADMIN-SYS'], ['Admin']);
        $this->seedUser('supply@local.test', 'Supply Head', $positions['POS-SUP-HEAD'], ['Supply Head']);
        $this->seedUser('custodian@local.test', 'Chief Property Custodian', $positions['POS-PC-CHIEF'], ['Property Custodian']);
        $this->seedUser('it.expert@local.test', 'IT Expert', $positions['POS-IT-EXPERT'], ['Admin']);
        $this->seedUser('director@local.test', 'Office Director', $positions['POS-DIRECTOR'], ['Property Custodian']);
        $this->seedUser('eng.custodian@local.test', 'Engineering Custodian', $positions['POS-COE-PC'], ['Property Custodian']);
        $this->seedUser('education.custodian@local.test', 'Education Custodian', $positions['POS-COED-PC'], ['Property Custodian']);
        $this->seedUser('business.custodian@local.test', 'Business Custodian', $positions['POS-BACC-PC'], ['Property Custodian']);
        $this->seedUser('registrar.custodian@local.test', 'Registrar Custodian', $positions['POS-REG-PC'], ['Property Custodian']);
        $this->seedUser('library.custodian@local.test', 'Library Custodian', $positions['POS-LIB-PC'], ['Property Custodian']);
        $this->seedUser('student.affairs.custodian@local.test', 'Student Affairs Custodian', $positions['POS-STUDENT-PC'], ['Property Custodian']);
        $this->seedUser('admin.office.custodian@local.test', 'Administration Custodian', $positions['POS-ADMIN-PC'], ['Property Custodian']);
        $this->seedUser('director.office.custodian@local.test', 'Director Office Custodian', $positions['POS-DIR-PC'], ['Property Custodian']);
        $this->seedUser('inventory.analyst@local.test', 'Inventory Analyst', $positions['POS-SPMO-ANL'], ['Supply Head']);
    }

    /**
     * @param  array<int, string>  $roles
     */
    protected function seedUser(string $email, string $name, Position $position, array $roles): void
    {
        $user = User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => 'password',
                'position_id' => $position->id,
                'email_verified_at' => CarbonImmutable::now(),
            ],
        );

        $user->syncRoles($roles);
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

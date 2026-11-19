<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Position;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            throw new \RuntimeException('Operational user seed data cannot be seeded in production.');
        }

        foreach (['Admin', 'Supply Head', 'Property Custodian'] as $roleName) {
            Role::findOrCreate($roleName);
        }

        $departments = [
            'ADMIN' => Department::query()->updateOrCreate(['code' => 'ADMIN'], ['name' => 'Administration Office', 'is_active' => true]),
            'IT' => Department::query()->updateOrCreate(['code' => 'IT'], ['name' => 'Information Technology Office', 'is_active' => true]),
            'SPMO' => Department::query()->updateOrCreate(['code' => 'SPMO'], ['name' => 'Supply and Property Management Office', 'is_active' => true]),
            'FMO' => Department::query()->updateOrCreate(['code' => 'FMO'], ['name' => 'Facilities Management Office', 'is_active' => true]),
        ];

        $positions = [
            'POS-ADMIN-SYS' => $this->seedPosition('POS-ADMIN-SYS', $departments['ADMIN'], 'System Administrator & Operations Manager'),
            'POS-IT-DIR' => $this->seedPosition('POS-IT-DIR', $departments['IT'], 'IT Director & Inventory Lead'),
            'POS-WH-SUP' => $this->seedPosition('POS-WH-SUP', $departments['SPMO'], 'Warehouse Supervisor'),
            'POS-PROC-OFF' => $this->seedPosition('POS-PROC-OFF', $departments['SPMO'], 'Procurement Officer'),
            'POS-LOG-COORD' => $this->seedPosition('POS-LOG-COORD', $departments['FMO'], 'Logistics Coordinator'),
        ];

        $users = [
            [
                'email' => 'neilwayne0514@gmail.com',
                'name' => 'Neil Wayne Cruz',
                'position' => $positions['POS-ADMIN-SYS'],
                'roles' => ['Admin'],
            ],
            [
                'email' => 'cruzneilwayne@gmail.com',
                'name' => 'Neil Wayne Cruz',
                'position' => $positions['POS-IT-DIR'],
                'roles' => ['Admin'],
            ],
            [
                'email' => 'norman.cruz0701@gmail.com',
                'name' => 'Norman Cruz',
                'position' => $positions['POS-WH-SUP'],
                'roles' => ['Supply Head'],
            ],
            [
                'email' => 'wendzcruz04@gmail.com',
                'name' => 'Wendy Cruz',
                'position' => $positions['POS-PROC-OFF'],
                'roles' => ['Supply Head'],
            ],
            [
                'email' => 'nathanweiltcruz1415@gmail.com',
                'name' => 'Nathan Weil Cruz',
                'position' => $positions['POS-LOG-COORD'],
                'roles' => ['Property Custodian'],
            ],
        ];

        foreach ($users as $definition) {
            $this->seedUser(
                email: $definition['email'],
                name: $definition['name'],
                position: $definition['position'],
                roles: $definition['roles'],
            );
        }
    }

    /**
     * @param  array<int, string>  $roles
     */
    protected function seedUser(string $email, string $name, Position $position, array $roles): void
    {
        $user = User::query()->firstOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make('password'),
                'position_id' => $position->id,
                'email_verified_at' => CarbonImmutable::now(),
                'is_active' => true,
            ],
        );

        if ($user->wasRecentlyCreated) {
            $user->syncRoles($roles);

            return;
        }

        $user->forceFill([
            'name' => $name,
            'position_id' => $position->id,
        ])->save();
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

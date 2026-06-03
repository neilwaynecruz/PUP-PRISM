<?php

namespace Database\Seeders;

use App\Enums\AssetStatus;
use App\Enums\ProductType;
use App\Models\Asset;
use App\Models\Category;
use App\Models\Department;
use App\Models\Origin;
use App\Models\Position;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class E2eSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (['Admin', 'Supply Head', 'Property Custodian'] as $roleName) {
            Role::findOrCreate($roleName);
        }

        $departments = [];
        foreach ([
            ['code' => 'SPMO', 'name' => 'Supply and Property Management Office'],
            ['code' => 'IT', 'name' => 'Information Technology Office'],
            ['code' => 'COE', 'name' => 'College of Engineering'],
        ] as $dept) {
            $departments[$dept['code']] = Department::query()->updateOrCreate(
                ['code' => $dept['code']],
                ['name' => $dept['name'], 'is_active' => true],
            );
        }

        $positions = [];
        foreach ([
            ['code' => 'POS-ADMIN', 'department' => 'IT', 'title' => 'System Administrator'],
            ['code' => 'POS-SUPPLY', 'department' => 'SPMO', 'title' => 'Supply Head'],
            ['code' => 'POS-CUSTODIAN', 'department' => 'SPMO', 'title' => 'Property Custodian'],
            ['code' => 'POS-ENGINEER', 'department' => 'COE', 'title' => 'Engineer'],
        ] as $pos) {
            $positions[$pos['code']] = $this->seedPosition(
                code: $pos['code'],
                department: $departments[$pos['department']],
                title: $pos['title'],
            );
        }

        $now = CarbonImmutable::now();

        $users = [];
        $userDefinitions = [
            ['email' => 'admin@e2e.test', 'name' => 'Admin User', 'position' => 'POS-ADMIN', 'roles' => ['Admin']],
            ['email' => 'supply@e2e.test', 'name' => 'Supply Head', 'position' => 'POS-SUPPLY', 'roles' => ['Supply Head']],
            ['email' => 'custodian@e2e.test', 'name' => 'Property Custodian', 'position' => 'POS-CUSTODIAN', 'roles' => ['Property Custodian']],
            ['email' => 'requester@e2e.test', 'name' => 'Requester User', 'position' => 'POS-ENGINEER', 'roles' => ['Property Custodian']],
            ['email' => 'recipient@e2e.test', 'name' => 'Recipient User', 'position' => 'POS-ENGINEER', 'roles' => []],
        ];

        foreach ($userDefinitions as $def) {
            $user = User::query()->updateOrCreate(
                ['email' => $def['email']],
                [
                    'name' => $def['name'],
                    'password' => 'password',
                    'position_id' => $positions[$def['position']]->id,
                    'email_verified_at' => $now,
                ],
            );
            $user->syncRoles($def['roles']);
            $users[$def['email']] = $user;
        }

        $origin = Origin::query()->updateOrCreate(['name' => 'Main Campus']);

        $categories = [];
        foreach (['Office Supplies', 'IT Equipment'] as $catName) {
            $categories[$catName] = Category::query()->updateOrCreate(['name' => $catName]);
        }

        $consumableProduct = Product::query()->updateOrCreate(
            ['sku' => 'CON-E2E-001'],
            [
                'name' => 'E2E Test Consumable',
                'category_id' => $categories['Office Supplies']->id,
                'origin_id' => $origin->id,
                'type' => ProductType::Consumable,
                'reorder_threshold' => 10,
                'is_active' => true,
            ],
        );

        ProductStock::query()->updateOrCreate(
            ['product_id' => $consumableProduct->id],
            ['on_hand_qty' => 100],
        );

        $assetProduct = Product::query()->updateOrCreate(
            ['sku' => 'AST-E2E-001'],
            [
                'name' => 'E2E Test Asset',
                'category_id' => $categories['IT Equipment']->id,
                'origin_id' => $origin->id,
                'type' => ProductType::Asset,
                'reorder_threshold' => 0,
                'is_active' => true,
            ],
        );

        $assetDefinitions = [
            ['tag_code' => 'AST-E2E-0001', 'position' => 'POS-CUSTODIAN', 'status' => AssetStatus::Available],
            ['tag_code' => 'AST-E2E-0002', 'position' => 'POS-ADMIN', 'status' => AssetStatus::Available],
        ];

        foreach ($assetDefinitions as $def) {
            Asset::query()->updateOrCreate(
                ['tag_code' => $def['tag_code']],
                [
                    'product_id' => $assetProduct->id,
                    'position_id' => $positions[$def['position']]->id,
                    'status' => $def['status'],
                ],
            );
        }
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

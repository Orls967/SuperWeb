<?php

declare(strict_types=1);

namespace Modules\ProServices\Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

final class ProServicesRbacSeeder extends Seeder
{
    public function run(): void
    {
        if (class_exists(Role::class)) {
            Role::firstOrCreate(['name' => 'project_manager', 'guard_name' => 'web']);
            Role::firstOrCreate(['name' => 'consultant', 'guard_name' => 'web']);
            Role::firstOrCreate(['name' => 'client', 'guard_name' => 'web']);
        }
    }
}

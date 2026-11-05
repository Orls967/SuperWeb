<?php

declare(strict_types=1);

namespace Modules\Forest\Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

final class ForestRbacSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            'forester',
            'timber_inspector',
            'timber_buyer',
        ];

        foreach ($roles as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
    }
}

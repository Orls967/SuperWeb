<?php

declare(strict_types=1);

namespace Tests\Acceptance\Support;

use App\Models\User;
use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Core\Domain\Models\Role;
use Tests\TestCase;

abstract class HospitalAcceptanceHelper extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(LedgerAccountsSeeder::class);
    }

    protected function createHospitalUser(string $roleName): User
    {
        Role::firstOrCreate(['name' => $roleName], ['label' => ucfirst($roleName)]);

        return User::factory()->create([
            'role' => $roleName,
        ]);
    }
}

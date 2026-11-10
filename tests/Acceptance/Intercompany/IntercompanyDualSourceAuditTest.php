<?php

declare(strict_types=1);

namespace Tests\Acceptance\Intercompany;

use App\Quality\Audit\Fixtures\GroupAuditCorruptionFixture;
use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IntercompanyDualSourceAuditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LedgerAccountsSeeder::class);
    }

    public function test_group_audit_passes_on_clean_data_and_fails_on_corrupted_fixture(): void
    {
        // 1. Kondisi bersih -> Sukses 0
        $this->artisan('group:audit')
            ->assertSuccessful()
            ->expectsOutputToContain('Group Consolidation audit PASSED with 0 discrepancy.');

        // 2. Injeksi sabotase korupsi data via fixture resmi
        $fixture = new GroupAuditCorruptionFixture;
        $expectedError = $fixture->corrupt();

        // 3. Command mendeteksi diskrepansi dan mengembalikan status FAILURE
        $this->artisan('group:audit')
            ->assertFailed()
            ->expectsOutputToContain($expectedError);
    }
}

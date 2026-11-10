<?php

declare(strict_types=1);

namespace Tests\Acceptance\Treasury;

use App\Quality\Audit\Fixtures\TreasuryAuditCorruptionFixture;
use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Treasury\Application\Services\TreasuryService;
use Tests\TestCase;

class TreasuryDualSourceAuditTest extends TestCase
{
    use RefreshDatabase;

    protected TreasuryService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LedgerAccountsSeeder::class);
        $this->service = app(TreasuryService::class);
    }

    public function test_treasury_audit_passes_on_clean_data_and_fails_on_corrupted_fixture(): void
    {
        // 1. Data Bersih -> Exit Code 0
        $this->service->registerCurrency('IDR', 'Indonesian Rupiah', 'Rp', 0);
        $this->service->registerCurrency('USD', 'US Dollar', '$', 2);
        $this->service->createBankAccount('ACC-AUDIT-CLEAN', 'Bank Mandiri', 'IDR', 100_000_000);
        $this->service->createCreditFacility('FAC-AUDIT-CLEAN', 'BCA', 1_000_000_000);

        $exitClean = $this->artisan('treasury:audit');
        $exitClean->assertExitCode(0);

        // 2. Corrupt Data via Fixture -> Exit Code != 0 (exit 1)
        $fixture = new TreasuryAuditCorruptionFixture;
        $expectedErrorFragment = $fixture->corrupt();

        $exitCorrupt = $this->artisan('treasury:audit');
        $exitCorrupt->assertExitCode(1);
    }
}

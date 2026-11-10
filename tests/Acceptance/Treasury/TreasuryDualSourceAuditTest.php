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

        $exitClean = $this->artisan('treasury:audit')->run();
        $this->assertSame(0, $exitClean);

        // 2. Corrupt Data via Fixture -> Exit Code != 0 (exit 1)
        $fixture = new TreasuryAuditCorruptionFixture;
        $expectedErrorFragment = $fixture->corrupt();

        $exitCorrupt = $this->artisan('treasury:audit')->run();
        $this->assertSame(1, $exitCorrupt);
    }

    public function test_treasury_t1_dataset_normal_and_shock_injection_maintains_zero_discrepancy(): void
    {
        $seeder = new \Modules\Treasury\database\seeders\TreasuryT1Seeder;
        $seeder->run();

        $exitNormal = $this->artisan('treasury:audit')->run();
        $this->assertSame(0, $exitNormal);

        $seeder->injectShock();

        $exitShock = $this->artisan('treasury:audit')->run();
        $this->assertSame(0, $exitShock);
    }
}

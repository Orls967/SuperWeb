<?php

declare(strict_types=1);

namespace Tests\Feature;

use Database\Seeders\EnterpriseUniverseSeeder;
use Database\Seeders\ValueChainUltraSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Modules\Banking\database\seeders\BankingSeeder;
use Modules\Party\database\seeders\PartySeeder;
use Tests\TestCase;

class MaintenanceAndResiliencePhase57BTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BankingSeeder::class);
        $this->seed(PartySeeder::class);
        $this->seed(EnterpriseUniverseSeeder::class);
        $this->seed(ValueChainUltraSeeder::class);
    }

    /**
     * 57B.1 & 57B.8: Audit platform health check & ledger balance invariance.
     */
    public function test_platform_health_check_passes_with_perfect_invariance(): void
    {
        $code = Artisan::call('super:health-check');
        $this->assertSame(0, $code);
    }

    /**
     * 57B.4: Test rate limiter configuration in AppServiceProvider.
     */
    public function test_rate_limiters_are_configured(): void
    {
        $this->assertNotNull(RateLimiter::limiter('transactions'));
        $this->assertNotNull(RateLimiter::limiter('wallet-pin'));
        $this->assertNotNull(RateLimiter::limiter('auth-attempts'));
        $this->assertNotNull(RateLimiter::limiter('exports-imports'));
    }

    /**
     * 57B.6: Unique entity seeders verification.
     */
    public function test_enterprise_universe_seeder_uniqueness_and_idempotency(): void
    {
        $countBefore = DB::table('pty_parties')->where('short_name', 'like', 'MMN-%')->count();
        $this->assertSame(100, $countBefore);

        // Run again to verify absolute idempotency
        $this->seed(EnterpriseUniverseSeeder::class);
        $countAfter = DB::table('pty_parties')->where('short_name', 'like', 'MMN-%')->count();
        $this->assertSame(100, $countAfter);
    }

    /**
     * 57B.9: Full regression of 12 value chain audits.
     */
    public function test_orchestrated_audits_remain_zero_discrepancy(): void
    {
        $code = Artisan::call('chain:audit-all');
        $this->assertSame(0, $code);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature;

use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class TwelveLinesComprehensiveAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_ecosystem_audit_12_lines_returns_zero_discrepancies(): void
    {
        $this->seed(LedgerAccountsSeeder::class);

        $exitCode = Artisan::call('ecosystem:audit-12-lines');
        $this->assertEquals(0, $exitCode);

        $output = Artisan::output();
        $this->assertStringContainsString('0 discrepancies across all 12 lines', $output);
    }
}

<?php

declare(strict_types=1);

use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Modules\Integration\Application\Services\CrossEcosystemWave2OrchestrationService;
use Modules\Integration\Domain\Events\CrossEcosystemWave2Event;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(LedgerAccountsSeeder::class);
});

test('(140.7) end-to-end multi-line single day cycle executes across all Wave 2 lines with event spine dispatches', function () {
    Event::fake([CrossEcosystemWave2Event::class]);

    $orchestrator = app(CrossEcosystemWave2OrchestrationService::class);

    $result = $orchestrator->executeFullDayCycle([
        'meter_code' => 'MTR-STADIUM-MAIN',
    ]);

    expect($result['status'])->toBe('SUCCESS')
        ->and($result['events_dispatched'])->toContain('egy.demand_surge')
        ->and($result['events_dispatched'])->toContain('tlx.isp_billed')
        ->and($result['events_dispatched'])->toContain('med.campaign_settled')
        ->and($result['events_dispatched'])->toContain('edu.cert_issued')
        ->and($result['events_dispatched'])->toContain('ret.cashback_issued')
        ->and(strlen($result['certificate_hash']))->toBe(64)
        ->and($result['qc_delivered'])->toBe('DELIVERED')
        ->and($result['cashback_earned_minor'])->toBe(7500000);

    Event::assertDispatched(CrossEcosystemWave2Event::class, 5);
});

<?php

declare(strict_types=1);

namespace Modules\Core\tests\Feature\Resilience;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Application\DTOs\Resilience\DrillExecutionDto;
use Modules\Core\Application\Services\Resilience\DisasterRecoveryDrillService;
use Modules\Core\Domain\Models\CoreDrDrillExecution;
use Tests\TestCase;

class DisasterRecoveryDrillTest extends TestCase
{
    use RefreshDatabase;

    public function test_executes_chaos_failover_drill_with_rpo_and_rto_within_strict_sla(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        /** @var DisasterRecoveryDrillService $service */
        $service = app(DisasterRecoveryDrillService::class);

        $dto = new DrillExecutionDto(
            drillType: 'FAILOVER_SIMULATION',
            primaryRegion: 'ap-southeast-3',
            targetDrRegion: 'ap-southeast-1',
            simulatedLagSeconds: 12,
            userId: $admin->id,
            metadata: ['scenario' => 'primary_power_outage_simulation']
        );

        $drill = $service->executeDrill($dto);

        $this->assertInstanceOf(CoreDrDrillExecution::class, $drill);
        $this->assertStringStartsWith('drill-failover-simulation-', $drill->drill_uuid);
        $this->assertSame('SUCCESS', $drill->status);
        $this->assertSame(0, $drill->divergence_count);
        $this->assertLessThanOrEqual(60, $drill->rpo_seconds);
        $this->assertLessThanOrEqual(900, $drill->rto_seconds);
        $this->assertNotEmpty($drill->checksum_hash);
        $this->assertSame(64, strlen($drill->checksum_hash));

        $this->assertDatabaseHas('core_dr_drill_executions', [
            'id' => $drill->id,
            'drill_type' => 'FAILOVER_SIMULATION',
            'status' => 'SUCCESS',
            'primary_region' => 'ap-southeast-3',
            'target_dr_region' => 'ap-southeast-1',
        ]);
    }
}

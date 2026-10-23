<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Core\Application\Services\UniversalEventSpineOutboxService;
use Modules\Core\Domain\Models\SimEventSpine;
use Tests\TestCase;

class TwelveLinesChaosResiliencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_mid_transaction_crash_rolls_back_cleanly(): void
    {
        DB::table('core_command_center_metrics')->insert([
            'metric_key' => 'chaos:baseline',
            'domain_code' => 'CORE',
            'metric_value_minor' => 100,
            'unit' => 'COUNT',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $initialCount = DB::table('core_command_center_metrics')->count();

        try {
            DB::transaction(function (): void {
                DB::table('core_command_center_metrics')->insert([
                    'metric_key' => 'chaos:transient',
                    'domain_code' => 'CORE',
                    'metric_value_minor' => 999,
                    'unit' => 'COUNT',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                throw new \RuntimeException('Simulated worker crash mid-transaction');
            });
        } catch (\RuntimeException $e) {
            // caught crash
        }

        $this->assertSame($initialCount, DB::table('core_command_center_metrics')->count());
        $this->assertNull(DB::table('core_command_center_metrics')->where('metric_key', 'chaos:transient')->first());
    }

    public function test_dlq_event_spine_resiliency_after_worker_failure(): void
    {
        $service = app(UniversalEventSpineOutboxService::class);
        $event = $service->publishEvent('chaos', 'chaos.worker.kill', ['shipment_id' => 999], 'evt:chaos:999');

        $this->assertInstanceOf(SimEventSpine::class, $event);
        $this->assertSame('evt:chaos:999', $event->idempotency_key);

        $attempts = 0;
        for ($i = 0; $i < 3; $i++) {
            try {
                $service->consumeEvent($event->id, 'chaos_consumer_group', function (): void {
                    throw new \RuntimeException('Worker SIGKILL emulation');
                });
            } catch (\RuntimeException $e) {
                $attempts++;
            }
        }

        $this->assertSame(3, $attempts);
    }
}

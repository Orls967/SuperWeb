<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Banking\Domain\Models\LedgerAccount;
use Modules\Core\Application\Services\UniversalEventSpineOutboxService;
use Modules\Core\Domain\Models\SimEventSpine;
use Tests\TestCase;

class TwelveLinesChaosResiliencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_mid_transaction_crash_rolls_back_cleanly(): void
    {
        LedgerAccount::query()->firstOrCreate(
            ['code' => 'chaos:vault:IDR'],
            [
                'uuid' => (string) Str::uuid(),
                'name' => 'Chaos Vault',
                'asset_code' => 'IDR',
                'kind' => 'ASSET',
                'allow_negative' => true,
                'cached_balance' => '0',
                'is_frozen' => false,
            ]
        );

        $initialCount = DB::table('bank_ledger_accounts')->count();

        try {
            DB::transaction(function (): void {
                DB::table('bank_ledger_accounts')->insert([
                    'uuid' => (string) Str::uuid(),
                    'code' => 'chaos:transient:IDR',
                    'name' => 'Transient Account',
                    'asset_code' => 'IDR',
                    'kind' => 'ASSET',
                    'allow_negative' => true,
                    'cached_balance' => '0',
                    'is_frozen' => false,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                throw new \RuntimeException('Simulated worker crash mid-transaction');
            });
        } catch (\RuntimeException $e) {
            // caught crash
        }

        $this->assertSame($initialCount, DB::table('bank_ledger_accounts')->count());
        $this->assertNull(DB::table('bank_ledger_accounts')->where('code', 'chaos:transient:IDR')->first());
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

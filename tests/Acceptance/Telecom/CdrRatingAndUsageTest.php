<?php

declare(strict_types=1);

namespace Tests\Acceptance\Telecom;

use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Modules\Tlx\Application\Services\CdrRatingService;
use Modules\Tlx\Contracts\IotDeviceRegistry;
use Modules\Tlx\Domain\Models\TelecomCdr;
use Tests\TestCase;

class CdrRatingAndUsageTest extends TestCase
{
    use RefreshDatabase;

    protected CdrRatingService $ratingService;

    protected IotDeviceRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LedgerAccountsSeeder::class);
        $this->ratingService = app(CdrRatingService::class);
        $this->registry = app(IotDeviceRegistry::class);
    }

    public function test_cdr_ingestion_stores_append_only_records(): void
    {
        $device = $this->registry->registerDevice([
            'device_code' => 'DEV-CDR-01',
            'device_type' => 'TELEMATICS',
            'owner_entity_id' => 'ENT-LOGISTICS-01',
            'using_module' => 'Telematics',
            'monthly_fee_minor' => 30000,
            'idempotency_key' => 'TLX-REG:DEV-CDR-01',
        ]);

        $cdr = $this->ratingService->ingestCdr([
            'device_id' => $device['id'],
            'session_id' => 'SESS-202610-0001',
            'started_at' => '2026-10-01 10:00:00',
            'ended_at' => '2026-10-01 10:05:00',
            'bytes_transferred' => 5000,
            'duration_seconds' => 300,
        ]);

        expect($cdr->session_id)->toBe('SESS-202610-0001')
            ->and($cdr->bytes_transferred)->toBe(5000)
            ->and($cdr->rated_bytes)->toBe(5120); // 5000 rounded up to next 1024 multiple = 5120

        expect(TelecomCdr::count())->toBe(1);
    }

    public function test_cdr_is_strictly_append_only_rejects_update_and_delete(): void
    {
        $device = $this->registry->registerDevice([
            'device_code' => 'DEV-CDR-IMMUTABLE',
            'device_type' => 'TELEMATICS',
            'owner_entity_id' => 'ENT-LOGISTICS-01',
            'using_module' => 'Telematics',
            'monthly_fee_minor' => 30000,
            'idempotency_key' => 'TLX-REG:DEV-CDR-IMMUTABLE',
        ]);

        $cdr = $this->ratingService->ingestCdr([
            'device_id' => $device['id'],
            'session_id' => 'SESS-IMM-01',
            'started_at' => '2026-10-01 10:00:00',
            'ended_at' => '2026-10-01 10:05:00',
            'bytes_transferred' => 2048,
            'duration_seconds' => 300,
        ]);

        $this->expectException(\RuntimeException::class);
        $cdr->update(['bytes_transferred' => 4096]);
    }

    public function test_cdr_delete_is_strictly_forbidden(): void
    {
        $device = $this->registry->registerDevice([
            'device_code' => 'DEV-CDR-NODELETE',
            'device_type' => 'TELEMATICS',
            'owner_entity_id' => 'ENT-LOGISTICS-01',
            'using_module' => 'Telematics',
            'monthly_fee_minor' => 30000,
            'idempotency_key' => 'TLX-REG:DEV-CDR-NODELETE',
        ]);

        $cdr = $this->ratingService->ingestCdr([
            'device_id' => $device['id'],
            'session_id' => 'SESS-DEL-01',
            'started_at' => '2026-10-01 10:00:00',
            'ended_at' => '2026-10-01 10:05:00',
            'bytes_transferred' => 1024,
            'duration_seconds' => 300,
        ]);

        $this->expectException(\RuntimeException::class);
        $cdr->delete();
    }

    public function test_duplicate_session_id_for_same_device_is_rejected(): void
    {
        $device = $this->registry->registerDevice([
            'device_code' => 'DEV-CDR-DUP',
            'device_type' => 'ENERGY_METER',
            'owner_entity_id' => 'ENT-ENERGY-01',
            'using_module' => 'Energy',
            'monthly_fee_minor' => 35000,
            'idempotency_key' => 'TLX-REG:DEV-CDR-DUP',
        ]);

        $this->ratingService->ingestCdr([
            'device_id' => $device['id'],
            'session_id' => 'SESS-DUP-01',
            'started_at' => '2026-10-01 11:00:00',
            'ended_at' => '2026-10-01 11:10:00',
            'bytes_transferred' => 1024,
            'duration_seconds' => 600,
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->ratingService->ingestCdr([
            'device_id' => $device['id'],
            'session_id' => 'SESS-DUP-01',
            'started_at' => '2026-10-01 11:00:00',
            'ended_at' => '2026-10-01 11:10:00',
            'bytes_transferred' => 2048,
            'duration_seconds' => 600,
        ]);
    }

    public function test_rating_edge_cases_round_up_to_next_1024_bytes(): void
    {
        $device = $this->registry->registerDevice([
            'device_code' => 'DEV-CDR-EDGE',
            'device_type' => 'BUILDING_SENSOR',
            'owner_entity_id' => 'ENT-PROPTECH-01',
            'using_module' => 'Proptech',
            'monthly_fee_minor' => 20000,
            'idempotency_key' => 'TLX-REG:DEV-CDR-EDGE',
        ]);

        // 0 bytes -> 0 rated
        $cdr0 = $this->ratingService->ingestCdr([
            'device_id' => $device['id'],
            'session_id' => 'SESS-E-0',
            'started_at' => '2026-10-01 12:00:00',
            'ended_at' => '2026-10-01 12:01:00',
            'bytes_transferred' => 0,
            'duration_seconds' => 60,
        ]);
        expect($cdr0->rated_bytes)->toBe(0);

        // 1 byte -> 1024 rated
        $cdr1 = $this->ratingService->ingestCdr([
            'device_id' => $device['id'],
            'session_id' => 'SESS-E-1',
            'started_at' => '2026-10-01 12:02:00',
            'ended_at' => '2026-10-01 12:03:00',
            'bytes_transferred' => 1,
            'duration_seconds' => 60,
        ]);
        expect($cdr1->rated_bytes)->toBe(1024);

        // 1024 bytes -> exactly 1024 rated
        $cdr1024 = $this->ratingService->ingestCdr([
            'device_id' => $device['id'],
            'session_id' => 'SESS-E-1024',
            'started_at' => '2026-10-01 12:04:00',
            'ended_at' => '2026-10-01 12:05:00',
            'bytes_transferred' => 1024,
            'duration_seconds' => 60,
        ]);
        expect($cdr1024->rated_bytes)->toBe(1024);

        // 1025 bytes -> 2048 rated
        $cdr1025 = $this->ratingService->ingestCdr([
            'device_id' => $device['id'],
            'session_id' => 'SESS-E-1025',
            'started_at' => '2026-10-01 12:06:00',
            'ended_at' => '2026-10-01 12:07:00',
            'bytes_transferred' => 1025,
            'duration_seconds' => 60,
        ]);
        expect($cdr1025->rated_bytes)->toBe(2048);
    }

    public function test_negative_bytes_or_duration_is_rejected(): void
    {
        $device = $this->registry->registerDevice([
            'device_code' => 'DEV-CDR-NEG',
            'device_type' => 'MINING_SENSOR',
            'owner_entity_id' => 'ENT-MINING-01',
            'using_module' => 'Mining',
            'monthly_fee_minor' => 40000,
            'idempotency_key' => 'TLX-REG:DEV-CDR-NEG',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->ratingService->ingestCdr([
            'device_id' => $device['id'],
            'session_id' => 'SESS-NEG-01',
            'started_at' => '2026-10-01 13:00:00',
            'ended_at' => '2026-10-01 13:05:00',
            'bytes_transferred' => -100,
            'duration_seconds' => 300,
        ]);
    }

    public function test_cdr_for_non_existent_device_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->ratingService->ingestCdr([
            'device_id' => 'NON-EXISTENT-UUID',
            'session_id' => 'SESS-GHOST-01',
            'started_at' => '2026-10-01 14:00:00',
            'ended_at' => '2026-10-01 14:05:00',
            'bytes_transferred' => 2048,
            'duration_seconds' => 300,
        ]);
    }

    public function test_monthly_usage_aggregation_calculates_excess_data(): void
    {
        $device = $this->registry->registerDevice([
            'device_code' => 'DEV-CDR-AGG',
            'device_type' => 'TELEMATICS',
            'owner_entity_id' => 'ENT-LOGISTICS-02',
            'using_module' => 'Telematics',
            'monthly_fee_minor' => 30000,
            'idempotency_key' => 'TLX-REG:DEV-CDR-AGG',
        ]);

        // Ingest multiple CDRs: 500 KB and 600 KB rated
        $this->ratingService->ingestCdr([
            'device_id' => $device['id'],
            'session_id' => 'SESS-AGG-1',
            'started_at' => '2026-10-02 08:00:00',
            'ended_at' => '2026-10-02 08:30:00',
            'bytes_transferred' => 500 * 1024,
            'duration_seconds' => 1800,
        ]);

        $this->ratingService->ingestCdr([
            'device_id' => $device['id'],
            'session_id' => 'SESS-AGG-2',
            'started_at' => '2026-10-02 09:00:00',
            'ended_at' => '2026-10-02 09:30:00',
            'bytes_transferred' => 600 * 1024,
            'duration_seconds' => 1800,
        ]);

        $summary = $this->ratingService->calculateMonthlyUsage('ENT-LOGISTICS-02', '2026-10', 1); // 1 MB quota

        // Total rated bytes = 1100 KB = 1,126,400 bytes = 1.1 MB. Quota = 1 MB (1,048,576 bytes).
        // Excess bytes = 77,824 bytes -> rounded up to 1 MB integer excess.
        expect($summary['total_consumed_bytes'])->toBe(1126400)
            ->and($summary['quota_mb'])->toBe(1)
            ->and($summary['excess_mb'])->toBe(1);
    }

    public function test_zero_excess_when_usage_within_quota(): void
    {
        $device = $this->registry->registerDevice([
            'device_code' => 'DEV-CDR-WITHIN',
            'device_type' => 'ENERGY_METER',
            'owner_entity_id' => 'ENT-ENERGY-02',
            'using_module' => 'Energy',
            'monthly_fee_minor' => 35000,
            'idempotency_key' => 'TLX-REG:DEV-CDR-WITHIN',
        ]);

        $this->ratingService->ingestCdr([
            'device_id' => $device['id'],
            'session_id' => 'SESS-WITHIN-1',
            'started_at' => '2026-10-03 08:00:00',
            'ended_at' => '2026-10-03 08:30:00',
            'bytes_transferred' => 500 * 1024,
            'duration_seconds' => 1800,
        ]);

        $summary = $this->ratingService->calculateMonthlyUsage('ENT-ENERGY-02', '2026-10', 5); // 5 MB quota
        expect($summary['excess_mb'])->toBe(0);
    }

    public function test_batch_cdr_rating_via_artisan_command(): void
    {
        $device = $this->registry->registerDevice([
            'device_code' => 'DEV-CDR-CMD',
            'device_type' => 'TELEMATICS',
            'owner_entity_id' => 'ENT-LOGISTICS-03',
            'using_module' => 'Telematics',
            'monthly_fee_minor' => 30000,
            'idempotency_key' => 'TLX-REG:DEV-CDR-CMD',
        ]);

        $this->ratingService->ingestCdr([
            'device_id' => $device['id'],
            'session_id' => 'SESS-CMD-01',
            'started_at' => '2026-10-04 10:00:00',
            'ended_at' => '2026-10-04 10:10:00',
            'bytes_transferred' => 2048,
            'duration_seconds' => 600,
        ]);

        $exitCode = Artisan::call('tlx:rate-cdr', [
            '--period' => '2026-10',
        ]);

        expect($exitCode)->toBe(0);
    }

    public function test_monthly_usage_aggregation_with_large_batch_over_1000_cdrs_calculates_correct_excess(): void
    {
        $device = $this->registry->registerDevice([
            'device_code' => 'DEV-CDR-LARGE-1',
            'device_type' => 'TELEMATICS',
            'owner_entity_id' => 'ENT-LARGE-01',
            'using_module' => 'Telematics',
            'monthly_fee_minor' => 30000,
            'idempotency_key' => 'TLX-REG:DEV-CDR-LARGE-1',
        ]);

        $rows = [];
        $totalBytes = 0;
        // 1050 CDRs, each 2048 bytes (rated 2048) -> total 2,150,400 bytes (~2.05 MB)
        for ($i = 1; $i <= 1050; $i++) {
            $rows[] = [
                'id' => (string) Str::uuid(),
                'device_id' => $device['id'],
                'session_id' => "SESS-LRG-{$i}",
                'started_at' => '2026-10-05 10:00:00',
                'ended_at' => '2026-10-05 10:05:00',
                'bytes_transferred' => 2048,
                'rated_bytes' => 2048,
                'duration_seconds' => 300,
                'created_at' => '2026-10-05 10:05:00',
                'updated_at' => '2026-10-05 10:05:00',
            ];
            $totalBytes += 2048;
        }
        DB::table('tlx_cdrs')->insert($rows);

        // Quota = 1 MB (1,048,576 bytes). Excess = 2,150,400 - 1,048,576 = 1,101,824 bytes -> 2 MB integer ceil excess.
        $summary = $this->ratingService->calculateMonthlyUsage('ENT-LARGE-01', '2026-10', 1);

        expect($summary['total_consumed_bytes'])->toBe($totalBytes)
            ->and($summary['quota_mb'])->toBe(1)
            ->and($summary['excess_mb'])->toBe(2);
    }

    public function test_monthly_usage_aggregation_with_large_batch_over_1000_cdrs_within_quota(): void
    {
        $device = $this->registry->registerDevice([
            'device_code' => 'DEV-CDR-LARGE-2',
            'device_type' => 'TELEMATICS',
            'owner_entity_id' => 'ENT-LARGE-02',
            'using_module' => 'Telematics',
            'monthly_fee_minor' => 30000,
            'idempotency_key' => 'TLX-REG:DEV-CDR-LARGE-2',
        ]);

        $rows = [];
        for ($i = 1; $i <= 1010; $i++) {
            $rows[] = [
                'id' => (string) Str::uuid(),
                'device_id' => $device['id'],
                'session_id' => "SESS-LRG2-{$i}",
                'started_at' => '2026-10-05 11:00:00',
                'ended_at' => '2026-10-05 11:05:00',
                'bytes_transferred' => 1024,
                'rated_bytes' => 1024,
                'duration_seconds' => 300,
                'created_at' => '2026-10-05 11:05:00',
                'updated_at' => '2026-10-05 11:05:00',
            ];
        }
        DB::table('tlx_cdrs')->insert($rows);

        // 1010 * 1024 bytes = 1,034,240 bytes < 1 MB (1,048,576 bytes). Quota = 1 MB -> Excess = 0.
        $summary = $this->ratingService->calculateMonthlyUsage('ENT-LARGE-02', '2026-10', 1);

        expect($summary['total_consumed_bytes'])->toBe(1034240)
            ->and($summary['quota_mb'])->toBe(1)
            ->and($summary['excess_mb'])->toBe(0);
    }
}

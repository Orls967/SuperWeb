<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Integration\Application\Services\DatabasePartitionScaleService;
use Tests\TestCase;

class DatabasePartitionScaleTest extends TestCase
{
    use RefreshDatabase;

    protected DatabasePartitionScaleService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(DatabasePartitionScaleService::class);
    }

    public function test_partition_switch_failure_rollback_edge_case(): void
    {
        // 1. Inconsistent row counts trigger rollback leaving no partial state (393.4 & 393.5 Edge Case)
        try {
            $this->service->executePartitionSwitch(
                partitionName: 'PART_LEDGER_TRANSACTIONS_2026_Q3',
                preRowCount: 500000,
                postRowCount: 499990 // Row count mismatch!
            );
            $this->fail('Expected exception for partition switch mismatch');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('safely rolled back to original partition', $e->getMessage());
        }

        // Verify rolled back partition state in DB
        $partition = DB::table('global_stress_partition_switches')->where('partition_name', 'PART_LEDGER_TRANSACTIONS_2026_Q3')->first();
        $this->assertNotNull($partition);
        $this->assertTrue((bool) $partition->switch_rolled_back);
        $this->assertEquals('ROLLED_BACK', $partition->switch_status);

        // 2. Successful switch preserves counts and commits (393.4)
        $committed = $this->service->executePartitionSwitch(
            partitionName: 'PART_LEDGER_TRANSACTIONS_2026_Q4',
            preRowCount: 600000,
            postRowCount: 600000
        );
        $this->assertEquals('COMMITTED', $committed->switch_status);
        $this->assertFalse((bool) $committed->switch_rolled_back);
    }

    public function test_archive_legal_hold_rejection_risk(): void
    {
        // 1. Archiving data with active legal hold fails (393.3, 393.4, 393.6 Risk)
        try {
            $this->service->archiveBatch(
                archiveBatchCode: 'ARC-COMMODITY-TRADE-2024',
                batchData: 'TRADE RECORDS DISPUTE',
                isLegalHoldActive: true // Active hold!
            );
            $this->fail('Expected exception for archiving data under legal hold');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('has active legal hold and cannot be archived', $e->getMessage());
        }

        // 2. Archiving data without legal hold succeeds with checksum (393.3 & 393.4)
        $archived = $this->service->archiveBatch(
            archiveBatchCode: 'ARC-COMMODITY-TRADE-2023',
            batchData: 'TRADE RECORDS RESOLVED',
            isLegalHoldActive: false
        );
        $this->assertTrue((bool) $archived->archive_permitted);
        $this->assertNotEmpty($archived->archive_checksum);
    }

    public function test_partition_archive_audit_clean_and_discrepancy(): void
    {
        // Healthy setup
        $this->service->executePartitionSwitch('PART_AUD', 100, 100);
        $this->service->archiveBatch('ARC_AUD', 'DATA', false);

        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);

        // Inject discrepancy: legal hold permitted archive
        DB::table('global_stress_archive_legal_holds')->insert([
            'archive_batch_code' => 'ARC-DEFECT-HOLD-LEAK',
            'is_legal_hold_active' => true,
            'archive_permitted' => true, // Discrepancy!
            'archive_checksum' => 'FAKE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $auditDiscrepant = $this->service->audit();
        $this->assertEquals('DISCREPANCY_DETECTED', $auditDiscrepant['status']);
        $this->assertGreaterThan(0, $auditDiscrepant['discrepancy_count']);
    }
}

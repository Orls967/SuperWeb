<?php

declare(strict_types=1);

namespace Tests\Unit\Algorithms;

use InvalidArgumentException;
use Modules\Shared\Domain\Algorithms\Inventory\ReorderPolicy;
use Modules\Shared\Domain\Algorithms\Inventory\ReorderPolicyCertificateVerifier;
use Modules\Shared\Domain\Algorithms\Inventory\ReorderPolicyResult;
use Tests\TestCase;

class ReorderPolicyTest extends TestCase
{
    /** U-14 & D82-1: Vektor tunggal spesifikasi VMI (s, S) */
    public function test_vmi_reorder_policy_exact_vectors(): void
    {
        $policy = new ReorderPolicy;

        // Vektor L=4, plafon 15.000.000 (PO otomatis)
        $input1 = [
            'd_mean' => 120,
            'sigma' => 30,
            'lead_time_days' => 4,
            'z' => 1.645,
            'review_period_days' => 7,
            'on_hand' => 400,
            'on_order' => 150,
            'backorder' => 50, // posisi = 400 + 150 - 50 = 500
            'pack_size' => 50,
            'unit_price_idr' => 12_000,
            'contract_ceiling_idr' => 15_000_000,
        ];

        $res1 = $policy->evaluate($input1);
        $this->assertSame(99, $res1->safetyStock);
        $this->assertSame(579, $res1->reorderPoint);
        $this->assertSame(1419, $res1->orderUpToLevel);
        $this->assertSame(500, $res1->netPosition);
        $this->assertSame(919, $res1->rawOrderQty);
        $this->assertSame(950, $res1->orderQty);
        $this->assertSame(11_400_000, $res1->totalCostIdr);
        $this->assertTrue($res1->autoPoAllowed);
        $this->assertFalse($res1->requiresApproval);

        // Plafon 10.000.000 (menunggu approval)
        $input2 = $input1;
        $input2['contract_ceiling_idr'] = 10_000_000;
        $res2 = $policy->evaluate($input2);
        $this->assertSame(950, $res2->orderQty);
        $this->assertSame(11_400_000, $res2->totalCostIdr);
        $this->assertFalse($res2->autoPoAllowed);
        $this->assertTrue($res2->requiresApproval);

        // Vektor L=5 -> SS 111 (110,35 -> 111), ROP 711, S 1.551
        $input3 = $input1;
        $input3['lead_time_days'] = 5;
        $res3 = $policy->evaluate($input3);
        $this->assertSame(111, $res3->safetyStock);
        $this->assertSame(711, $res3->reorderPoint);
        $this->assertSame(1551, $res3->orderUpToLevel);
    }

    /** PR2 & U-14: Sertifikat rusak melempar exception */
    public function test_corrupted_certificate_throws_exception(): void
    {
        $input = [
            'd_mean' => 120,
            'sigma' => 30,
            'lead_time_days' => 4,
            'z' => 1.645,
            'review_period_days' => 7,
            'on_hand' => 500,
            'on_order' => 0,
            'backorder' => 0,
            'pack_size' => 50,
            'unit_price_idr' => 12_000,
            'contract_ceiling_idr' => 15_000_000,
        ];

        $corruptResult = new ReorderPolicyResult(
            safetyStock: 99,
            reorderPoint: 579,
            orderUpToLevel: 1419,
            netPosition: 500,
            rawOrderQty: 919,
            orderQty: 950,
            totalCostIdr: 11_400_000,
            autoPoAllowed: true,
            requiresApproval: false,
            certificate: 'tampered-hash-certificate'
        );

        $this->expectException(InvalidArgumentException::class);
        ReorderPolicyCertificateVerifier::verify($input, $corruptResult);
    }
}

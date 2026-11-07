<?php

declare(strict_types=1);

namespace Tests\Unit\Algorithms;

use InvalidArgumentException;
use Modules\Shared\Domain\Algorithms\Settlement\BundleSspAllocationInput;
use Modules\Shared\Domain\Algorithms\Settlement\BundleSspAllocator;
use Modules\Shared\Domain\Algorithms\Settlement\BundleSspCertificate;
use Modules\Shared\Domain\Algorithms\Settlement\BundleSspVerifier;
use Tests\TestCase;

final class BundleSspAllocatorTest extends TestCase
{
    private BundleSspAllocator $allocator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->allocator = new BundleSspAllocator;
    }

    public function test_hamilton_hare_ssp_allocation_and_invariants(): void
    {
        // Bundle Rp 2.999.999; SSP: [1.8jt, 1.5jt, 400rb, 300rb] -> Total SSP 4jt
        // 5% Platform Fee (500 bps)
        $input = new BundleSspAllocationInput(
            bundlePriceIdr: 2_999_999,
            platformFeeBps: 500,
            components: [
                ['name' => 'ticket_pass', 'ssp_idr' => 1_800_000, 'vendor_id' => 'VEN-01'],
                ['name' => 'hotel_glamp', 'ssp_idr' => 1_500_000, 'vendor_id' => 'HTL-01'],
                ['name' => 'fnb_voucher', 'ssp_idr' => 400_000, 'vendor_id' => 'RESTO-01'],
                ['name' => 'shuttle_vip', 'ssp_idr' => 300_000, 'vendor_id' => 'SHUT-01'],
            ]
        );

        $result = $this->allocator->allocate($input);

        $this->assertSame(2_999_999, $result->bundlePriceIdr);
        $this->assertSame(4_000_000, $result->totalSspIdr);

        // Konservasi Uang Invarian
        $totalAlloc = 0;
        $totalFee = 0;
        $totalNet = 0;
        foreach ($result->items as $item) {
            $totalAlloc += $item['allocated_amount_idr'];
            $totalFee += $item['platform_fee_idr'];
            $totalNet += $item['vendor_net_idr'];
            $this->assertSame($item['allocated_amount_idr'], $item['platform_fee_idr'] + $item['vendor_net_idr']);
        }

        $this->assertSame(2_999_999, $totalAlloc);
        $this->assertSame(2_999_999, $totalFee + $totalNet);
        $this->assertTrue($result->certificate->isValid());
    }

    public function test_tampered_bundle_certificate_is_rejected(): void
    {
        $input = new BundleSspAllocationInput(
            bundlePriceIdr: 1_000_000,
            platformFeeBps: 1000,
            components: [
                ['name' => 'item_a', 'ssp_idr' => 600_000, 'vendor_id' => 'V1'],
                ['name' => 'item_b', 'ssp_idr' => 400_000, 'vendor_id' => 'V2'],
            ]
        );

        $result = $this->allocator->allocate($input);

        $items = $result->items;
        $items[0]['allocated_amount_idr'] += 100; // manipulasi alokasi

        $tamperedCert = new BundleSspCertificate(
            bundlePriceIdr: $result->certificate->bundlePriceIdr,
            totalSspIdr: $result->certificate->totalSspIdr,
            platformFeeBps: $result->certificate->platformFeeBps,
            items: $items,
            hmacSignature: $result->certificate->hmacSignature
        );

        $this->expectException(InvalidArgumentException::class);
        BundleSspVerifier::verify($tamperedCert);
    }
}

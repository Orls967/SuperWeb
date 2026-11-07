<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Settlement;

use InvalidArgumentException;

/**
 * Algoritma V6: BundleSspAllocator (Standar Akuntansi IFRS 15 / PSAK 72)
 *
 * Mengalokasikan harga transaksi paket bundling multi-vendor berdasarkan
 * Standalone Selling Price (SSP) relatif menggunakan metode Hamilton-Hare (Largest Remainder)
 * tanpa selisih pembulatan 1 rupiah pun, serta menghitung pembagian hasil vendor net dan platform fee.
 */
final class BundleSspAllocator
{
    public function allocate(BundleSspAllocationInput $input): BundleSspAllocationResult
    {
        if ($input->bundlePriceIdr <= 0) {
            throw new InvalidArgumentException('Harga paket bundling harus lebih besar dari 0.');
        }

        if (empty($input->components)) {
            throw new InvalidArgumentException('Komponen bundling tidak boleh kosong.');
        }

        if ($input->platformFeeBps < 0 || $input->platformFeeBps > 10_000) {
            throw new InvalidArgumentException('Platform fee basis points harus berada di rentang 0..10000.');
        }

        // 1. Validasi & Hitung Total SSP
        $totalSspIdr = 0;
        foreach ($input->components as $idx => $comp) {
            if (! isset($comp['ssp_idr']) || $comp['ssp_idr'] <= 0) {
                throw new InvalidArgumentException("SSP untuk komponen index {$idx} harus lebih besar dari 0.");
            }
            $totalSspIdr += (int) $comp['ssp_idr'];
        }

        // 2. Tahap 1 Hamilton-Hare: Hitung Alokasi Dasar dan Sisa Fraksional (Remainder)
        $allocatedItems = [];
        $totalBaseAllocated = 0;
        $remainders = [];

        foreach ($input->components as $idx => $comp) {
            $ssp = (int) $comp['ssp_idr'];

            // Quota presisi tinggi (skala integer 1.000.000 untuk representasi fraksi tanpa float)
            // Quota_int = (bundlePrice * ssp) / totalSsp
            $baseAlloc = (int) intdiv($input->bundlePriceIdr * $ssp, $totalSspIdr);
            $totalBaseAllocated += $baseAlloc;

            // Sisa modulo sebagai remainder
            $moduloRemainder = ($input->bundlePriceIdr * $ssp) % $totalSspIdr;

            $remainders[] = [
                'index' => $idx,
                'remainder' => $moduloRemainder,
                'ssp' => $ssp,
            ];

            $allocatedItems[$idx] = [
                'name' => (string) ($comp['name'] ?? "item_{$idx}"),
                'vendor_id' => (string) ($comp['vendor_id'] ?? 'INTERNAL'),
                'ssp_idr' => $ssp,
                'allocated_amount_idr' => $baseAlloc,
                'platform_fee_idr' => 0,
                'vendor_net_idr' => 0,
            ];
        }

        // 3. Tahap 2 Hamilton-Hare: Alokasikan Residu 1 Rupiah ke Item dengan Remainder Terbesar
        $residual = $input->bundlePriceIdr - $totalBaseAllocated;

        // Urutkan remainder menurun (jika remainder sama, prioritaskan SSP lebih besar, lalu index lebih kecil)
        usort($remainders, function (array $a, array $b): int {
            if ($a['remainder'] !== $b['remainder']) {
                return $b['remainder'] <=> $a['remainder'];
            }
            if ($a['ssp'] !== $b['ssp']) {
                return $b['ssp'] <=> $a['ssp'];
            }

            return $a['index'] <=> $b['index'];
        });

        for ($i = 0; $i < $residual; $i++) {
            $targetIndex = $remainders[$i % count($remainders)]['index'];
            $allocatedItems[$targetIndex]['allocated_amount_idr'] += 1;
        }

        // 4. Hitung Platform Fee dan Vendor Net untuk Setiap Komponen
        foreach ($allocatedItems as $idx => &$item) {
            $alloc = $item['allocated_amount_idr'];
            $fee = (int) intdiv($alloc * $input->platformFeeBps, 10_000);
            $net = $alloc - $fee;

            $item['platform_fee_idr'] = $fee;
            $item['vendor_net_idr'] = $net;
        }
        unset($item);

        // 5. Terbitkan Sertifikat Komputasi
        $signature = BundleSspVerifier::sign(
            bundlePriceIdr: $input->bundlePriceIdr,
            totalSspIdr: $totalSspIdr,
            platformFeeBps: $input->platformFeeBps,
            items: $allocatedItems
        );

        $certificate = new BundleSspCertificate(
            bundlePriceIdr: $input->bundlePriceIdr,
            totalSspIdr: $totalSspIdr,
            platformFeeBps: $input->platformFeeBps,
            items: $allocatedItems,
            hmacSignature: $signature
        );

        return new BundleSspAllocationResult(
            bundlePriceIdr: $input->bundlePriceIdr,
            totalSspIdr: $totalSspIdr,
            items: $allocatedItems,
            certificate: $certificate
        );
    }
}

<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Market;

use RuntimeException;

/**
 * Verifier sertifikat matematis untuk Call Auction Clearing (D24-3, PR2).
 *
 * Memverifikasi secara independen tanpa mempercayai solver:
 * 1. Setiap bid tereksekusi memenuhi bid.price >= clearing_price;
 * 2. Setiap ask tereksekusi memenuhi ask.price <= clearing_price;
 * 3. Bid tak tereksekusi tertinggi < ask tak tereksekusi terendah (atau salah satu sisi kosong);
 * 4. Volume kliring = min(D(p*), S(p*));
 * 5. Konsistensi alokasi kuantitas tidak melebihi pesanan asli dan seimbang (sum bid = sum ask = volume).
 *
 * Melempar RuntimeException bila sertifikat tidak valid (PR2).
 */
final class CallAuctionClearingCertificateVerifier
{
    /**
     * @param  list<array{id: string|int, quantity: int, price: int, created_at: int|string}>  $bids
     * @param  list<array{id: string|int, quantity: int, price: int, created_at: int|string}>  $asks
     */
    public function verify(array $bids, array $asks, CallAuctionClearingResult $result): bool
    {
        $pStar = $result->clearingPrice;
        $vStar = $result->clearingVolume;

        if ($vStar < 0 || $pStar < 0) {
            throw new RuntimeException('Sertifikat CallAuction tidak valid: harga atau volume negatif.');
        }

        if ($vStar === 0) {
            foreach ($result->bidAllocations as $qty) {
                if ($qty !== 0) {
                    throw new RuntimeException('Sertifikat CallAuction tidak valid: alokasi bid non-nol saat volume 0.');
                }
            }
            foreach ($result->askAllocations as $qty) {
                if ($qty !== 0) {
                    throw new RuntimeException('Sertifikat CallAuction tidak valid: alokasi ask non-nol saat volume 0.');
                }
            }

            return true;
        }

        // 1. Verifikasi alokasi bid
        $sumBidAlloc = 0;
        $highestUnfilledBid = null;
        foreach ($bids as $b) {
            $id = $b['id'];
            $alloc = $result->bidAllocations[$id] ?? 0;
            if ($alloc < 0 || $alloc > (int) $b['quantity']) {
                throw new RuntimeException("Sertifikat CallAuction tidak valid: alokasi bid {$id} melanggar kapasitas.");
            }
            if ($alloc > 0 && (int) $b['price'] < $pStar) {
                throw new RuntimeException("Sertifikat CallAuction tidak valid: bid {$id} tereksekusi di bawah harga kliring.");
            }
            $sumBidAlloc += $alloc;

            $unfilled = (int) $b['quantity'] - $alloc;
            if ($unfilled > 0) {
                if ($highestUnfilledBid === null || (int) $b['price'] > $highestUnfilledBid) {
                    $highestUnfilledBid = (int) $b['price'];
                }
            }
        }

        if ($sumBidAlloc !== $vStar) {
            throw new RuntimeException('Sertifikat CallAuction tidak valid: total alokasi bid tidak sama dengan volume kliring.');
        }

        // 2. Verifikasi alokasi ask
        $sumAskAlloc = 0;
        $lowestUnfilledAsk = null;
        foreach ($asks as $a) {
            $id = $a['id'];
            $alloc = $result->askAllocations[$id] ?? 0;
            if ($alloc < 0 || $alloc > (int) $a['quantity']) {
                throw new RuntimeException("Sertifikat CallAuction tidak valid: alokasi ask {$id} melanggar kapasitas.");
            }
            if ($alloc > 0 && (int) $a['price'] > $pStar) {
                throw new RuntimeException("Sertifikat CallAuction tidak valid: ask {$id} tereksekusi di atas harga kliring.");
            }
            $sumAskAlloc += $alloc;

            $unfilled = (int) $a['quantity'] - $alloc;
            if ($unfilled > 0) {
                if ($lowestUnfilledAsk === null || (int) $a['price'] < $lowestUnfilledAsk) {
                    $lowestUnfilledAsk = (int) $a['price'];
                }
            }
        }

        if ($sumAskAlloc !== $vStar) {
            throw new RuntimeException('Sertifikat CallAuction tidak valid: total alokasi ask tidak sama dengan volume kliring.');
        }

        // 3. Verifikasi kondisi sertifikat tidak ada crossing sisa pesanan
        if ($highestUnfilledBid !== null && $lowestUnfilledAsk !== null && $highestUnfilledBid >= $lowestUnfilledAsk) {
            throw new RuntimeException('Sertifikat CallAuction tidak valid: sisa bid dan ask masih saling crossing.');
        }

        // 4. Verifikasi volume sama dengan min(D(p*), S(p*))
        $actualD = 0;
        foreach ($bids as $b) {
            if ($b['price'] >= $pStar) {
                $actualD += (int) $b['quantity'];
            }
        }

        $actualS = 0;
        foreach ($asks as $a) {
            if ($a['price'] <= $pStar) {
                $actualS += (int) $a['quantity'];
            }
        }

        if ($vStar !== min($actualD, $actualS)) {
            throw new RuntimeException('Sertifikat CallAuction tidak valid: volume kliring tidak sama dengan min(D(p*), S(p*)).');
        }

        // 5. Verifikasi matched trades konsisten
        $sumTrades = 0;
        foreach ($result->trades as $trade) {
            if ($trade['price'] !== $pStar) {
                throw new RuntimeException('Sertifikat CallAuction tidak valid: harga trade tidak sama dengan harga kliring.');
            }
            $sumTrades += (int) $trade['quantity'];
        }

        if ($sumTrades !== $vStar) {
            throw new RuntimeException('Sertifikat CallAuction tidak valid: total kuantitas trade tidak sama dengan volume kliring.');
        }

        return true;
    }
}

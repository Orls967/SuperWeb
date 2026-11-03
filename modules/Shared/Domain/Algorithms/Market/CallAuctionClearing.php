<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Market;

/**
 * Algoritma Lelang Periodik Harga Seragam (Call Auction Clearing) (D24-3).
 *
 * Menentukan harga kliring seragam yang:
 * 1. Memaksimalkan volume transaksi tereksekusi min(D(p), S(p));
 * 2. Meminimalkan ketimpangan permintaan dan penawaran |D(p) - S(p)|;
 * 3. Mengambil titik tengah interval harga bila ada rentang, dibulatkan HalfUp ke kelipatan Rp 10/kg;
 * 4. Mengalokasikan volume berdasarkan prioritas harga terbaik, lalu waktu terdaftar lebih awal (penawar marginal parsial);
 * 5. Menghasilkan sertifikat matematis validitas kliring.
 */
final class CallAuctionClearing
{
    /**
     * @param list<array{id: string|int, quantity: int, price: int, created_at: int|string}> $bids
     * @param list<array{id: string|int, quantity: int, price: int, created_at: int|string}> $asks
     */
    public function solve(array $bids, array $asks): CallAuctionClearingResult
    {
        if ($bids === [] || $asks === []) {
            return new CallAuctionClearingResult(
                clearingPrice: 0,
                clearingVolume: 0,
                bidAllocations: [],
                askAllocations: [],
                trades: [],
                certificate: [
                    'clearing_price' => 0,
                    'clearing_volume' => 0,
                    'highest_unexecuted_bid_price' => null,
                    'lowest_unexecuted_ask_price' => null,
                    'is_valid' => true,
                ],
            );
        }

        // 1. Kumpulkan seluruh titik harga unik
        $priceSet = [];
        foreach ($bids as $bid) {
            $priceSet[(int) $bid['price']] = true;
        }
        foreach ($asks as $ask) {
            $priceSet[(int) $ask['price']] = true;
        }
        $prices = array_keys($priceSet);
        sort($prices, SORT_NUMERIC);

        // Fungsi permintaan agregat D(p) dan penawaran agregat S(p)
        $demandAt = function (float|int $p) use ($bids): int {
            $sum = 0;
            foreach ($bids as $bid) {
                if ($bid['price'] >= $p) {
                    $sum += (int) $bid['quantity'];
                }
            }

            return $sum;
        };

        $supplyAt = function (float|int $p) use ($asks): int {
            $sum = 0;
            foreach ($asks as $ask) {
                if ($ask['price'] <= $p) {
                    $sum += (int) $ask['quantity'];
                }
            }

            return $sum;
        };

        // 2. Evaluasi titik diskrit dan interval antar titik
        // Setiap kandidat direpresentasikan sebagai tuple: [volume, -imbalance, p_min, p_max]
        $candidates = [];

        foreach ($prices as $p) {
            $d = $demandAt($p);
            $s = $supplyAt($p);
            $v = min($d, $s);
            $imb = abs($d - $s);
            $candidates[] = [
                'volume' => $v,
                'imbalance' => $imb,
                'p_min' => $p,
                'p_max' => $p,
            ];
        }

        $count = count($prices);
        for ($i = 0; $i < $count - 1; $i++) {
            $pLow = $prices[$i];
            $pHigh = $prices[$i + 1];
            $pMid = ($pLow + $pHigh) / 2.0;

            $d = $demandAt($pMid);
            $s = $supplyAt($pMid);
            $v = min($d, $s);
            $imb = abs($d - $s);
            $candidates[] = [
                'volume' => $v,
                'imbalance' => $imb,
                'p_min' => $pLow,
                'p_max' => $pHigh,
            ];
        }

        // Cari volume maksimum
        $maxVolume = 0;
        foreach ($candidates as $c) {
            if ($c['volume'] > $maxVolume) {
                $maxVolume = $c['volume'];
            }
        }

        if ($maxVolume <= 0) {
            return new CallAuctionClearingResult(
                clearingPrice: 0,
                clearingVolume: 0,
                bidAllocations: array_fill_keys(array_column($bids, 'id'), 0),
                askAllocations: array_fill_keys(array_column($asks, 'id'), 0),
                trades: [],
                certificate: [
                    'clearing_price' => 0,
                    'clearing_volume' => 0,
                    'highest_unexecuted_bid_price' => null,
                    'lowest_unexecuted_ask_price' => null,
                    'is_valid' => true,
                ],
            );
        }

        // Cari minimum imbalance pada volume maksimum
        $minImbalance = PHP_INT_MAX;
        foreach ($candidates as $c) {
            if ($c['volume'] === $maxVolume && $c['imbalance'] < $minImbalance) {
                $minImbalance = $c['imbalance'];
            }
        }

        // Kumpulkan batas interval harga terbaik
        $optPMin = PHP_INT_MAX;
        $optPMax = PHP_INT_MIN;
        foreach ($candidates as $c) {
            if ($c['volume'] === $maxVolume && $c['imbalance'] === $minImbalance) {
                if ($c['p_min'] < $optPMin) {
                    $optPMin = $c['p_min'];
                }
                if ($c['p_max'] > $optPMax) {
                    $optPMax = $c['p_max'];
                }
            }
        }

        // Titik tengah interval, dibulatkan HalfUp ke kelipatan Rp 10/kg
        $midpoint = ($optPMin + $optPMax) / 2.0;
        $clearingPrice = (int) (round($midpoint / 10.0, 0, PHP_ROUND_HALF_UP) * 10);

        // 3. Alokasi volume ke penawar (asks dan bids)
        // Asks diurutkan: harga lebih rendah lebih dulu, lalu created_at lebih awal
        $sortedAsks = $asks;
        usort($sortedAsks, function (array $a, array $b): int {
            if ($a['price'] !== $b['price']) {
                return $a['price'] <=> $b['price'];
            }
            if ($a['created_at'] !== $b['created_at']) {
                return $a['created_at'] <=> $b['created_at'];
            }

            return $a['id'] <=> $b['id'];
        });

        // Bids diurutkan: harga lebih tinggi lebih dulu, lalu created_at lebih awal
        $sortedBids = $bids;
        usort($sortedBids, function (array $a, array $b): int {
            if ($a['price'] !== $b['price']) {
                return $b['price'] <=> $a['price'];
            }
            if ($a['created_at'] !== $b['created_at']) {
                return $a['created_at'] <=> $b['created_at'];
            }

            return $a['id'] <=> $b['id'];
        });

        $askAllocations = [];
        foreach ($asks as $ask) {
            $askAllocations[$ask['id']] = 0;
        }

        $bidAllocations = [];
        foreach ($bids as $bid) {
            $bidAllocations[$bid['id']] = 0;
        }

        $remainingToFillAsks = $maxVolume;
        foreach ($sortedAsks as $ask) {
            if ($ask['price'] <= $clearingPrice && $remainingToFillAsks > 0) {
                $allocated = min((int) $ask['quantity'], $remainingToFillAsks);
                $askAllocations[$ask['id']] = $allocated;
                $remainingToFillAsks -= $allocated;
            }
        }

        $remainingToFillBids = $maxVolume;
        foreach ($sortedBids as $bid) {
            if ($bid['price'] >= $clearingPrice && $remainingToFillBids > 0) {
                $allocated = min((int) $bid['quantity'], $remainingToFillBids);
                $bidAllocations[$bid['id']] = $allocated;
                $remainingToFillBids -= $allocated;
            }
        }

        // 4. Buat daftar transaksi berpasangan (matched trades)
        $trades = [];
        $askIdx = 0;
        $bidIdx = 0;
        $askRemaining = [];
        foreach ($sortedAsks as $a) {
            $askRemaining[$a['id']] = $askAllocations[$a['id']] ?? 0;
        }
        $bidRemaining = [];
        foreach ($sortedBids as $b) {
            $bidRemaining[$b['id']] = $bidAllocations[$b['id']] ?? 0;
        }

        while ($askIdx < count($sortedAsks) && $bidIdx < count($sortedBids)) {
            $currentAsk = $sortedAsks[$askIdx];
            $currentBid = $sortedBids[$bidIdx];

            $askId = $currentAsk['id'];
            $bidId = $currentBid['id'];

            $availAsk = $askRemaining[$askId] ?? 0;
            $availBid = $bidRemaining[$bidId] ?? 0;

            if ($availAsk === 0) {
                $askIdx++;

                continue;
            }
            if ($availBid === 0) {
                $bidIdx++;

                continue;
            }

            $tradeQty = min($availAsk, $availBid);
            $trades[] = [
                'bid_id' => $bidId,
                'ask_id' => $askId,
                'quantity' => $tradeQty,
                'price' => $clearingPrice,
            ];

            $askRemaining[$askId] -= $tradeQty;
            $bidRemaining[$bidId] -= $tradeQty;
        }

        // 5. Sertifikat matematis
        $highestUnexecutedBidPrice = null;
        foreach ($bids as $bid) {
            $unfilled = (int) $bid['quantity'] - ($bidAllocations[$bid['id']] ?? 0);
            if ($unfilled > 0) {
                if ($highestUnexecutedBidPrice === null || $bid['price'] > $highestUnexecutedBidPrice) {
                    $highestUnexecutedBidPrice = (int) $bid['price'];
                }
            }
        }

        $lowestUnexecutedAskPrice = null;
        foreach ($asks as $ask) {
            $unfilled = (int) $ask['quantity'] - ($askAllocations[$ask['id']] ?? 0);
            if ($unfilled > 0) {
                if ($lowestUnexecutedAskPrice === null || $ask['price'] < $lowestUnexecutedAskPrice) {
                    $lowestUnexecutedAskPrice = (int) $ask['price'];
                }
            }
        }

        $certificate = [
            'clearing_price' => $clearingPrice,
            'clearing_volume' => $maxVolume,
            'highest_unexecuted_bid_price' => $highestUnexecutedBidPrice,
            'lowest_unexecuted_ask_price' => $lowestUnexecutedAskPrice,
            'is_valid' => true,
        ];

        $result = new CallAuctionClearingResult(
            clearingPrice: $clearingPrice,
            clearingVolume: $maxVolume,
            bidAllocations: $bidAllocations,
            askAllocations: $askAllocations,
            trades: $trades,
            certificate: $certificate,
        );

        (new CallAuctionClearingCertificateVerifier())->verify($bids, $asks, $result);

        return $result;
    }
}


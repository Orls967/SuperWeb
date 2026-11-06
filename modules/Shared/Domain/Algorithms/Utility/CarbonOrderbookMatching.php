<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Utility;

use InvalidArgumentException;

/**
 * Engine pencocokan orderbook kredit karbon internal (Price-Time Priority)
 * Beroperasi 100% integer basis points / minor units (Zero-Float).
 * Menghasilkan jejak transaksi audit bersertifikat kriptografis SHA-256.
 */
final class CarbonOrderbookMatching
{
    /**
     * @param array<array{
     *     id: int|string,
     *     party_id: int|string,
     *     price_minor_per_ton: int,
     *     quantity_kg_co2e: int,
     *     timestamp_epoch: int
     * }> $buyOrders Daftar penawaran beli (bids)
     * @param array<array{
     *     id: int|string,
     *     party_id: int|string,
     *     price_minor_per_ton: int,
     *     quantity_kg_co2e: int,
     *     timestamp_epoch: int
     * }> $sellOrders Daftar penawaran jual (asks)
     */
    public function match(array $buyOrders, array $sellOrders): CarbonOrderbookMatchingResult
    {
        // Validasi dan normalisasi bids: sort Price DESC, Time ASC
        $normalizedBids = [];
        foreach ($buyOrders as $b) {
            if ($b['quantity_kg_co2e'] <= 0 || $b['price_minor_per_ton'] <= 0) {
                throw new InvalidArgumentException('Kuantitas dan harga order beli harus > 0');
            }
            $normalizedBids[] = [
                'id' => $b['id'],
                'party_id' => $b['party_id'],
                'price' => (int) $b['price_minor_per_ton'],
                'remaining_kg' => (int) $b['quantity_kg_co2e'],
                'time' => (int) $b['timestamp_epoch'],
            ];
        }

        usort($normalizedBids, function ($a, $b) {
            if ($a['price'] !== $b['price']) {
                return $b['price'] <=> $a['price']; // Harga tertinggi dulu
            }

            return $a['time'] <=> $b['time']; // Waktu terawal dulu
        });

        // Validasi dan normalisasi asks: sort Price ASC, Time ASC
        $normalizedAsks = [];
        foreach ($sellOrders as $s) {
            if ($s['quantity_kg_co2e'] <= 0 || $s['price_minor_per_ton'] <= 0) {
                throw new InvalidArgumentException('Kuantitas dan harga order jual harus > 0');
            }
            $normalizedAsks[] = [
                'id' => $s['id'],
                'party_id' => $s['party_id'],
                'price' => (int) $s['price_minor_per_ton'],
                'remaining_kg' => (int) $s['quantity_kg_co2e'],
                'time' => (int) ($s['timestamp_epoch'] ?? $s['time'] ?? 0),
            ];
        }

        usort($normalizedAsks, function ($a, $b) {
            if ($a['price'] !== $b['price']) {
                return $a['price'] <=> $b['price']; // Harga terendah dulu
            }

            return $a['time'] <=> $b['time']; // Waktu terawal dulu
        });

        $matchedTrades = [];
        $totalTradedKg = 0;
        $totalTradeValueIdr = 0;

        $bidIdx = 0;
        $askIdx = 0;
        $bidCount = count($normalizedBids);
        $askCount = count($normalizedAsks);

        while ($bidIdx < $bidCount && $askIdx < $askCount) {
            $currentBid = &$normalizedBids[$bidIdx];
            $currentAsk = &$normalizedAsks[$askIdx];

            // Cek kesesuaian harga: Bid Price >= Ask Price
            if ($currentBid['price'] >= $currentAsk['price']) {
                // Harga eksekusi = harga order pasif yang sudah menunggu lebih dulu di orderbook (maker price)
                $executionPrice = $currentAsk['time'] <= $currentBid['time']
                    ? $currentAsk['price']
                    : $currentBid['price'];

                $matchedKg = min($currentBid['remaining_kg'], $currentAsk['remaining_kg']);

                // Nilai transaksi = (matchedKg * executionPrice) / 1000 (karena price adalah per ton / 1000 kg)
                $tradeValueIdr = (int) intdiv($matchedKg * $executionPrice, 1000);

                $matchedTrades[] = [
                    'buyer_order_id' => $currentBid['id'],
                    'buyer_party_id' => $currentBid['party_id'],
                    'seller_order_id' => $currentAsk['id'],
                    'seller_party_id' => $currentAsk['party_id'],
                    'matched_kg_co2e' => $matchedKg,
                    'execution_price_minor' => $executionPrice,
                    'trade_value_idr' => $tradeValueIdr,
                ];

                $totalTradedKg += $matchedKg;
                $totalTradeValueIdr += $tradeValueIdr;

                $currentBid['remaining_kg'] -= $matchedKg;
                $currentAsk['remaining_kg'] -= $matchedKg;

                if ($currentBid['remaining_kg'] === 0) {
                    $bidIdx++;
                }
                if ($currentAsk['remaining_kg'] === 0) {
                    $askIdx++;
                }
            } else {
                // Spread negatif (bid < ask), tidak ada lagi order yang dapat cocok
                break;
            }
        }

        // Susun payload kanonikal untuk sertifikat SHA-256
        $tradeSignatures = [];
        foreach ($matchedTrades as $t) {
            $tradeSignatures[] = sprintf(
                'B:%s|S:%s|KG:%d|PX:%d|VAL:%d',
                $t['buyer_order_id'],
                $t['seller_order_id'],
                $t['matched_kg_co2e'],
                $t['execution_price_minor'],
                $t['trade_value_idr']
            );
        }

        $canonicalPayload = sprintf(
            'CARBON-MATCH|TRADES:%d|TOTAL_KG:%d|TOTAL_VAL:%d|DETAILS:[%s]',
            count($matchedTrades),
            $totalTradedKg,
            $totalTradeValueIdr,
            implode(';', $tradeSignatures)
        );

        $certificateHash = hash('sha256', $canonicalPayload);

        return new CarbonOrderbookMatchingResult(
            matchedTrades: $matchedTrades,
            totalTradedKg: $totalTradedKg,
            totalTradeValueIdr: $totalTradeValueIdr,
            remainingBids: array_slice($normalizedBids, $bidIdx),
            remainingAsks: array_slice($normalizedAsks, $askIdx),
            certificateHash: $certificateHash,
            canonicalPayload: $canonicalPayload,
        );
    }
}

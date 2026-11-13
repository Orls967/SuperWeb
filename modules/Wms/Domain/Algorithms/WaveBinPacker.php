<?php

declare(strict_types=1);

namespace Modules\Wms\Domain\Algorithms;

use InvalidArgumentException;
use Modules\Wms\Domain\Certificates\WaveBinPackerCertificate;

/**
 * Algoritma J2: Perencanaan wave per (carrier, cutoff) memakai First-Fit Decreasing (FFD).
 *
 * Kompleksitas: O(N log N + N * W) dengan N = jumlah order dan W = jumlah wave per kelompok.
 * Aritmetika: 100% bilangan bulat. Tie-break pengurutan: ukuran menurun, lalu id menaik.
 * Batas: capacity >= 1, order dengan size > capacity ditempatkan ke wave tunggal berstatus OVERSIZE.
 */
final class WaveBinPacker
{
    public const string STATUS_STANDARD = 'STANDARD';

    public const string STATUS_OVERSIZE = 'OVERSIZE';

    /**
     * @param  int  $capacity  Kapasitas wave C >= 1
     * @param  list<array{id: string|int, size: int, carrier: string, cutoff: int}>  $orders
     * @return array{
     *     waves: list<array{
     *         wave_index: int,
     *         carrier: string,
     *         cutoff: int,
     *         status: string,
     *         load: int,
     *         capacity: int,
     *         items: list<array{id: string|int, size: int}>
     *     }>,
     *     certificate: WaveBinPackerCertificate
     * }
     */
    public function pack(int $capacity, array $orders): array
    {
        if ($capacity <= 0) {
            throw new InvalidArgumentException('INVALID_WAVE_CAPACITY');
        }

        $normalized = [];
        foreach ($orders as $order) {
            $size = (int) $order['size'];
            if ($size <= 0) {
                throw new InvalidArgumentException('INVALID_ORDER_SIZE');
            }
            $normalized[] = [
                'id' => $order['id'],
                'size' => $size,
                'carrier' => (string) $order['carrier'],
                'cutoff' => (int) $order['cutoff'],
            ];
        }

        // Kelompokkan per (carrier, cutoff) secara deterministik
        $groups = [];
        foreach ($normalized as $order) {
            $key = $order['carrier'].'|'.$order['cutoff'];
            $groups[$key]['carrier'] = $order['carrier'];
            $groups[$key]['cutoff'] = $order['cutoff'];
            $groups[$key]['orders'][] = $order;
        }
        ksort($groups);

        $allWaves = [];
        $steps = [];
        $waveCounter = 1;

        foreach ($groups as $group) {
            $groupOrders = $group['orders'];
            // Urutkan ukuran menurun, tie-break id menaik
            usort($groupOrders, static function (array $a, array $b): int {
                if ($a['size'] !== $b['size']) {
                    return $b['size'] <=> $a['size'];
                }

                return (string) $a['id'] <=> (string) $b['id'];
            });

            /** @var list<array{wave_index: int, carrier: string, cutoff: int, status: string, load: int, capacity: int, items: list<array{id: string|int, size: int}>}> $groupWaves */
            $groupWaves = [];

            foreach ($groupOrders as $order) {
                if ($order['size'] > $capacity) {
                    $oversizeWave = [
                        'wave_index' => $waveCounter++,
                        'carrier' => $group['carrier'],
                        'cutoff' => $group['cutoff'],
                        'status' => self::STATUS_OVERSIZE,
                        'load' => $order['size'],
                        'capacity' => $capacity,
                        'items' => [['id' => $order['id'], 'size' => $order['size']]],
                    ];
                    $groupWaves[] = $oversizeWave;
                    $steps[] = [
                        'order_id' => $order['id'],
                        'size' => $order['size'],
                        'assigned_wave' => $oversizeWave['wave_index'],
                        'mode' => self::STATUS_OVERSIZE,
                    ];

                    continue;
                }

                $placed = false;
                foreach ($groupWaves as &$wave) {
                    if ($wave['status'] === self::STATUS_STANDARD && ($wave['load'] + $order['size']) <= $capacity) {
                        $wave['items'][] = ['id' => $order['id'], 'size' => $order['size']];
                        $wave['load'] += $order['size'];
                        $placed = true;
                        $steps[] = [
                            'order_id' => $order['id'],
                            'size' => $order['size'],
                            'assigned_wave' => $wave['wave_index'],
                            'mode' => 'FIRST_FIT',
                        ];
                        break;
                    }
                }
                unset($wave);

                if (! $placed) {
                    $newWave = [
                        'wave_index' => $waveCounter++,
                        'carrier' => $group['carrier'],
                        'cutoff' => $group['cutoff'],
                        'status' => self::STATUS_STANDARD,
                        'load' => $order['size'],
                        'capacity' => $capacity,
                        'items' => [['id' => $order['id'], 'size' => $order['size']]],
                    ];
                    $groupWaves[] = $newWave;
                    $steps[] = [
                        'order_id' => $order['id'],
                        'size' => $order['size'],
                        'assigned_wave' => $newWave['wave_index'],
                        'mode' => 'NEW_WAVE',
                    ];
                }
            }

            foreach ($groupWaves as $gw) {
                $allWaves[] = $gw;
            }
        }

        $canonicalInput = [
            'capacity' => $capacity,
            'orders' => $normalized,
        ];
        $result = ['waves' => $allWaves];

        $certificate = WaveBinPackerCertificate::issue($canonicalInput, $steps, $result);
        $certificate->verifyOrFail();

        return [
            'waves' => $allWaves,
            'certificate' => $certificate,
        ];
    }
}

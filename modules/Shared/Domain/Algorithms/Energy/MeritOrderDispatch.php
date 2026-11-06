<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Energy;

use InvalidArgumentException;

/**
 * E2 Energy\MeritOrderDispatch
 *
 * Unit commitment dan merit-order dispatch:
 * Unit wajib-jalan (committed) minimal pada min-stable, sisa kebutuhan diisi
 * urutan biaya marjinal menaik (tie-break id), solar dibatasi ketersediaan jam itu;
 * surplus → curtailment unit termurah-variabel (energi terbarukan dikurangi bila wajib-jalan tidak bisa turun).
 *
 * Kompleksitas: O(U log U) di mana U adalah jumlah unit pembangkit.
 */
final class MeritOrderDispatch
{
    public function __construct(
        private readonly MeritOrderDispatchCertificateVerifier $verifier = new MeritOrderDispatchCertificateVerifier
    ) {}

    /**
     * @param  list<array{
     *     id: string,
     *     capacity_mw: int,
     *     available_capacity_mw?: int,
     *     min_stable_mw?: int,
     *     marginal_cost: int,
     *     is_must_run?: bool
     * }>  $units
     * @param  int  $loadMw  Kebutuhan beban sistem dalam MW
     */
    public function dispatch(array $units, int $loadMw): MeritOrderDispatchResult
    {
        if ($loadMw < 0) {
            throw new InvalidArgumentException("Beban MW tidak boleh negatif: {$loadMw}");
        }

        // Normalisasi unit
        $normalized = [];
        $totalAvailableCapacity = 0;
        $totalMustRunMin = 0;

        foreach ($units as $u) {
            $id = (string) $u['id'];
            $capacity = (int) $u['capacity_mw'];
            $available = isset($u['available_capacity_mw']) ? (int) $u['available_capacity_mw'] : $capacity;
            $minStable = isset($u['min_stable_mw']) ? (int) $u['min_stable_mw'] : 0;
            $marginalCost = (int) $u['marginal_cost'];
            $isMustRun = (bool) ($u['is_must_run'] ?? false);

            if ($available > $capacity) {
                $available = $capacity;
            }
            if ($minStable > $available && $available > 0) {
                $minStable = $available;
            }

            $normalized[$id] = [
                'id' => $id,
                'capacity_mw' => $capacity,
                'available_capacity_mw' => $available,
                'min_stable_mw' => $minStable,
                'marginal_cost' => $marginalCost,
                'is_must_run' => $isMustRun,
                'dispatched_mw' => 0,
                'curtailed_mw' => 0,
                'cost_thousand_idr' => 0,
            ];

            $totalAvailableCapacity += $available;
            if ($isMustRun) {
                $totalMustRunMin += $minStable;
            }
        }

        if ($loadMw > $totalAvailableCapacity) {
            throw new InvalidArgumentException("Defisit kapasitas: beban ({$loadMw} MW) melebihi kapasitas tersedia ({$totalAvailableCapacity} MW)");
        }

        // Tahap 1: Commit must-run units pada min_stable
        $currentDispatched = 0;
        foreach ($normalized as $id => &$u) {
            if ($u['is_must_run'] && $u['min_stable_mw'] > 0) {
                $alloc = min($u['min_stable_mw'], $loadMw);
                $u['dispatched_mw'] = $alloc;
                $currentDispatched += $alloc;
            }
        }
        unset($u);

        $remainingLoad = $loadMw - $currentDispatched;

        // Tahap 2: Urutkan unit berdasarkan marginal_cost menaik, tie-break id
        $sortedUnits = array_values($normalized);
        usort($sortedUnits, function ($a, $b) {
            if ($a['marginal_cost'] !== $b['marginal_cost']) {
                return $a['marginal_cost'] <=> $b['marginal_cost'];
            }

            return strcmp((string) $a['id'], (string) $b['id']);
        });

        // Alokasikan sisa beban ke unit termurah
        foreach ($sortedUnits as $su) {
            if ($remainingLoad <= 0) {
                break;
            }
            $id = $su['id'];
            $currentAlloc = $normalized[$id]['dispatched_mw'];
            $avail = $normalized[$id]['available_capacity_mw'];
            $min = $normalized[$id]['min_stable_mw'];

            // Jika unit belum aktif dan memiliki min_stable
            if ($currentAlloc === 0 && $min > 0) {
                if ($remainingLoad < $min) {
                    // Jika sisa beban kurang dari min stable unit non-must-run, cari alternatif atau cek unit lain
                    continue;
                }
            }

            $spare = $avail - $currentAlloc;
            if ($spare <= 0) {
                continue;
            }

            $fill = min($spare, $remainingLoad);
            $normalized[$id]['dispatched_mw'] += $fill;
            $remainingLoad -= $fill;
        }

        // Jika masih ada sisa karena batasan min_stable pada unit tersisa, penuhi dengan unit berikutnya
        if ($remainingLoad > 0) {
            foreach ($sortedUnits as $su) {
                if ($remainingLoad <= 0) {
                    break;
                }
                $id = $su['id'];
                $spare = $normalized[$id]['available_capacity_mw'] - $normalized[$id]['dispatched_mw'];
                if ($spare > 0) {
                    $fill = min($spare, $remainingLoad);
                    $normalized[$id]['dispatched_mw'] += $fill;
                    $remainingLoad -= $fill;
                }
            }
        }

        // Hitung curtailment untuk energi terbarukan / unit dengan kapasitas tersedia > dispatched
        $totalCurtailed = 0;
        $totalCost = 0;
        $allocations = [];

        foreach ($units as $u) {
            $id = (string) $u['id'];
            $node = $normalized[$id];
            $curtailed = max(0, $node['available_capacity_mw'] - $node['dispatched_mw']);
            // Curtailment tercatat hanya jika unit terbarukan / biaya 0 atau jika ada kelebihan daya
            if ($node['marginal_cost'] === 0) {
                $node['curtailed_mw'] = $curtailed;
                $totalCurtailed += $curtailed;
            } else {
                $node['curtailed_mw'] = 0;
            }
            $cost = $node['dispatched_mw'] * $node['marginal_cost'];
            $node['cost_thousand_idr'] = $cost;
            $totalCost += $cost;
            $allocations[] = $node;
        }

        $digest = hash('sha256', json_encode([
            'load' => $loadMw,
            'allocations' => $allocations,
            'cost' => $totalCost,
        ], JSON_THROW_ON_ERROR));

        $result = new MeritOrderDispatchResult(
            loadMw: $loadMw,
            totalDispatchedMw: $loadMw,
            totalCurtailedMw: $totalCurtailed,
            totalCostThousandIdr: $totalCost,
            allocations: $allocations,
            certificate: [
                'load_mw' => $loadMw,
                'total_dispatched_mw' => $loadMw,
                'total_curtailed_mw' => $totalCurtailed,
                'total_cost_thousand_idr' => $totalCost,
                'digest' => $digest,
            ]
        );

        $this->verifier->verify($result);

        return $result;
    }
}

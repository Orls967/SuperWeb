<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Energy;

use Modules\Shared\Domain\Math\Exceptions\InvalidCertificateException;

/**
 * Certificate Verifier for E2 Energy\MeritOrderDispatch.
 *
 * Enforces (PR2):
 *  1. Σ dispatch = beban (ekuilibrium daya).
 *  2. Tiap unit: dispatched_mw == 0 atau min_stable <= dispatched_mw <= available_capacity.
 *  3. Unit wajib-jalan (must_run): dispatched_mw >= min_stable.
 *  4. Merit-order: tidak ada unit lebih murah yang punya sisa kapasitas sementara
 *     unit lebih mahal dijalankan di atas min_stable.
 */
final class MeritOrderDispatchCertificateVerifier
{
    public function verify(MeritOrderDispatchResult $result): true
    {
        $sumDispatched = 0;
        $sumCurtailed = 0;
        $sumCost = 0;

        foreach ($result->allocations as $unit) {
            $disp = $unit['dispatched_mw'];
            $avail = $unit['available_capacity_mw'];
            $min = $unit['min_stable_mw'];

            if ($disp < 0) {
                throw new InvalidCertificateException("Dispatch unit {$unit['id']} tidak boleh negatif: {$disp}");
            }

            if ($unit['is_must_run'] && $disp < $min) {
                throw new InvalidCertificateException("Unit wajib jalan {$unit['id']} dijalankan di bawah min stable ({$disp} < {$min})");
            }

            if ($disp > 0 && $disp < $min) {
                throw new InvalidCertificateException("Unit {$unit['id']} aktif tetapi di bawah min stable ({$disp} < {$min})");
            }

            if ($disp > $avail) {
                throw new InvalidCertificateException("Unit {$unit['id']} dijalankan melampaui kapasitas tersedia ({$disp} > {$avail})");
            }

            $expectedCost = $disp * $unit['marginal_cost'];
            if ($unit['cost_thousand_idr'] !== $expectedCost) {
                throw new InvalidCertificateException("Biaya unit {$unit['id']} ({$unit['cost_thousand_idr']}) tidak cocok ({$expectedCost})");
            }

            $sumDispatched += $disp;
            $sumCurtailed += $unit['curtailed_mw'];
            $sumCost += $expectedCost;
        }

        if ($sumDispatched !== $result->loadMw) {
            throw new InvalidCertificateException("Σ dispatch ({$sumDispatched}) tidak sama dengan beban ({$result->loadMw})");
        }

        if ($sumDispatched !== $result->totalDispatchedMw) {
            throw new InvalidCertificateException('Total dispatched tidak konsisten dengan rincian');
        }

        if ($sumCost !== $result->totalCostThousandIdr) {
            throw new InvalidCertificateException('Total biaya tidak konsisten dengan rincian');
        }

        // Merit order optimality check:
        // No unit A with marginal_cost < unit B has available spare capacity while B is dispatched above min_stable
        $count = count($result->allocations);
        for ($i = 0; $i < $count; $i++) {
            $uA = $result->allocations[$i];
            $spareA = $uA['available_capacity_mw'] - $uA['dispatched_mw'];
            if ($spareA <= 0) {
                continue;
            }

            for ($j = 0; $j < $count; $j++) {
                if ($i === $j) {
                    continue;
                }
                $uB = $result->allocations[$j];
                if ($uA['marginal_cost'] < $uB['marginal_cost']) {
                    $dispatchedAboveMinB = $uB['dispatched_mw'] - ($uB['is_must_run'] ? $uB['min_stable_mw'] : 0);
                    if ($dispatchedAboveMinB > 0) {
                        throw new InvalidCertificateException(
                            "Pelanggaran merit order: unit lebih murah {$uA['id']} (biaya {$uA['marginal_cost']}) memiliki sisa {$spareA} MW, sementara unit lebih mahal {$uB['id']} (biaya {$uB['marginal_cost']}) berjalan di atas min ({$dispatchedAboveMinB} MW)"
                        );
                    }
                }
            }
        }

        return true;
    }
}

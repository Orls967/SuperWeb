<?php

declare(strict_types=1);

namespace Modules\Wms\Http\ViewModels;

use Illuminate\Support\Collection;
use Modules\Wms\Models\RoboticsRoiProposal;

/**
 * ViewModel portofolio proposal ROI otomasi robotika (satuan minor integer IDR tanpa float).
 */
final readonly class RoboticsRoiPortfolioViewModel
{
    /**
     * @param  Collection<int, RoboticsRoiProposal>  $proposals
     * @return array{
     *     total:int,
     *     approved_count:int,
     *     pending_count:int,
     *     negative_npv_count:int,
     *     total_capex_idr:string,
     *     total_npv_idr:string,
     *     rows:list<array{id:int, proposal_code:string, title:string, status:string, capex_idr:string, npv_idr:string, is_negative_npv:bool, irr_bps:int, payback_months:int, requires_justification:bool}>
     * }
     */
    public function present(Collection $proposals): array
    {
        $approved = 0;
        $pending = 0;
        $negative = 0;
        $totalCapexMinor = 0;
        $totalNpvMinor = 0;
        $rows = [];

        foreach ($proposals as $proposal) {
            $status = (string) $proposal->status;
            if ($status === 'approved') {
                $approved++;
            } else {
                $pending++;
            }

            $capexMinor = (int) $proposal->capex_minor;
            $npvMinor = (int) $proposal->npv_minor;
            $isNegative = $npvMinor < 0;
            if ($isNegative) {
                $negative++;
            }

            $totalCapexMinor += $capexMinor;
            $totalNpvMinor += $npvMinor;

            $rows[] = [
                'id' => (int) $proposal->id,
                'proposal_code' => (string) $proposal->proposal_code,
                'title' => (string) $proposal->title,
                'status' => $status,
                'capex_idr' => self::formatMinorIdr($capexMinor),
                'npv_idr' => self::formatMinorIdr($npvMinor),
                'is_negative_npv' => $isNegative,
                'irr_bps' => (int) $proposal->irr_bps,
                'payback_months' => (int) $proposal->payback_months,
                'requires_justification' => $isNegative && trim((string) ($proposal->justification ?? '')) === '',
            ];
        }

        return [
            'total' => $proposals->count(),
            'approved_count' => $approved,
            'pending_count' => $pending,
            'negative_npv_count' => $negative,
            'total_capex_idr' => self::formatMinorIdr($totalCapexMinor),
            'total_npv_idr' => self::formatMinorIdr($totalNpvMinor),
            'rows' => $rows,
        ];
    }

    /**
     * Memformat nilai minor integer (sen) ke string IDR tanpa operasi floating-point.
     */
    public static function formatMinorIdr(int $amountMinor): string
    {
        $sign = $amountMinor < 0 ? '-' : '';
        $absMinor = abs($amountMinor);
        $wholeIdr = intdiv($absMinor, 100);
        $cents = $absMinor % 100;

        return sprintf('%sRp %s,%02d', $sign, number_format($wholeIdr, 0, ',', '.'), $cents);
    }
}

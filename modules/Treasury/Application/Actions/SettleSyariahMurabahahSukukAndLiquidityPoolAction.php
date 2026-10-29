<?php

declare(strict_types=1);

namespace Modules\Treasury\Application\Actions;

use DomainException;
use Illuminate\Support\Facades\DB;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Contracts\Ledger;
use Modules\Banking\Support\Posting;
use Modules\Shared\Application\BaseAction;

class SettleSyariahMurabahahSukukAndLiquidityPoolAction extends BaseAction
{
    public function __construct(
        protected ?Ledger $ledger = null
    ) {}

    /**
     * Owner-module Treasury action for Lini 19 Syariah Murabahah fixed-margin contracts,
     * asset-backed Sukuk Ijarah coupon distribution, and global cash liquidity pool sweeps.
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function execute(array $params): array
    {
        return DB::transaction(function () use ($params): array {
            $sukukSeriesCode = (string) ($params['sukuk_series_code'] ?? 'TRS-SUKUK-2026-A');
            $ribaRateBps = (int) ($params['riba_rate_bps'] ?? 0);
            if ($ribaRateBps > 0) {
                throw new DomainException('Treasury Syariah desk violation: interest-bearing riba coupon is prohibited');
            }

            $sukukPrincipalMinor = (int) ($params['sukuk_principal_minor'] ?? 400000000);
            $ijarahAssetValueMinor = (int) ($params['ijarah_asset_value_minor'] ?? 500000000);
            if ($ijarahAssetValueMinor < $sukukPrincipalMinor) {
                throw new DomainException('Sukuk Ijarah requires 100% tangible underlying asset backing');
            }

            $ijarahRentalYieldBps = (int) ($params['ijarah_rental_yield_bps'] ?? 650);
            $periodicUjrahDistributionMinor = intdiv($sukukPrincipalMinor * $ijarahRentalYieldBps, 10000);
            $idleCashSweepMinor = (int) ($params['idle_cash_sweep_minor'] ?? 50000000);

            if ($periodicUjrahDistributionMinor > 0 && $this->ledger !== null) {
                $this->ledger->post(new PostingDTO(
                    type: 'WAVE3_SYARIAH_FINANCE_POOL',
                    description: "Treasury Sukuk Ijarah ujrah distribution {$sukukSeriesCode}",
                    idempotencyKey: hash('sha256', "TRS:W3:SUKUK:{$sukukSeriesCode}:{$periodicUjrahDistributionMinor}"),
                    entries: Posting::lines()
                        ->debit('wave3:syariah_murabahah_receivable:IDR', $periodicUjrahDistributionMinor)
                        ->credit('wave3:syariah_margin_revenue:IDR', $periodicUjrahDistributionMinor)
                        ->toEntries('IDR'),
                    referenceType: 'TRS_SUKUK_IJARAH',
                    referenceId: $sukukSeriesCode,
                ));
            }

            $certificateHash = hash('sha256', "{$sukukSeriesCode}:{$sukukPrincipalMinor}:{$periodicUjrahDistributionMinor}");

            $this->audit(
                action: 'trs.wave3.syariah_sukuk_and_liquidity_settled',
                newValues: ['sukuk_series_code' => $sukukSeriesCode, 'ujrah_minor' => $periodicUjrahDistributionMinor],
                correlationId: "trs_w3_{$sukukSeriesCode}",
                impactType: 'treasury_syariah'
            );

            return [
                'sukuk_series_code' => $sukukSeriesCode,
                'sukuk_principal_minor' => $sukukPrincipalMinor,
                'asset_coverage_bps' => $sukukPrincipalMinor > 0 ? intdiv($ijarahAssetValueMinor * 10000, $sukukPrincipalMinor) : 10000,
                'periodic_ujrah_distribution_minor' => $periodicUjrahDistributionMinor,
                'idle_cash_sweep_minor' => $idleCashSweepMinor,
                'sukuk_certificate_hash' => $certificateHash,
            ];
        });
    }
}

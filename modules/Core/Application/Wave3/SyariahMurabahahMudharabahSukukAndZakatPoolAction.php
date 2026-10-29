<?php

declare(strict_types=1);

namespace Modules\Core\Application\Wave3;

use DomainException;
use Illuminate\Support\Facades\DB;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Contracts\Ledger;
use Modules\Banking\Support\Posting;
use Modules\Shared\Application\BaseAction;

class SyariahMurabahahMudharabahSukukAndZakatPoolAction extends BaseAction
{
    private const NON_HALAL_SECTORS = ['GAMBLING', 'ALCOHOL', 'RIBA_LENDING', 'TOBACCO', 'WEAPONS'];

    public function __construct(
        protected ?Ledger $ledger = null
    ) {}

    /**
     * Enforces DSN-MUI / AAOIFI Shariah compliance invariants across Murabahah cost-plus financing,
     * Mudharabah profit-loss sharing, asset-backed Sukuk Ijarah, BMT microfinance, and Zakat/Wakaf pools (Fase 161-165).
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function execute(array $params): array
    {
        return DB::transaction(function () use ($params): array {
            $akadCode = (string) ($params['akad_code'] ?? 'W3-SYR-001');
            $sectorCode = strtoupper((string) ($params['underlying_sector'] ?? 'HALAL_MANUFACTURING'));
            $ribaInterestBps = (int) ($params['riba_interest_bps'] ?? 0);

            // 1. Fase 161 & 165: Anti-Riba & Halal Sector Shariah Screening Gate
            if ($ribaInterestBps > 0) {
                throw new DomainException("Shariah compliance violation: riba interest ({$ribaInterestBps} bps) is strictly forbidden in Islamic contracts");
            }

            if (in_array($sectorCode, self::NON_HALAL_SECTORS, true)) {
                throw new DomainException("Shariah screening rejected: sector '{$sectorCode}' is non-compliant with Shariah principles");
            }

            // 2. Fase 161: Murabahah Cost-Plus Fixed Ceiling & Ta'zir Charity Routing
            $assetCostMinor = (int) ($params['murabahah_asset_cost_minor'] ?? 200000000);
            $agreedMarginMinor = (int) ($params['murabahah_margin_minor'] ?? 30000000);
            $lateTazirPenaltyMinor = (int) ($params['late_tazir_penalty_minor'] ?? 0);

            if ($assetCostMinor < 0 || $agreedMarginMinor < 0 || $lateTazirPenaltyMinor < 0) {
                throw new DomainException('Murabahah cost, margin, and charity penalty amounts must be non-negative');
            }

            $murabahahSellingPriceMinor = $assetCostMinor + $agreedMarginMinor;
            // Crucial Shariah invariant: late ta'zir penalty NEVER enters bank revenue; 100% routed to charity pool
            $recognizedHalalMarginMinor = $agreedMarginMinor;
            $charityPoolAllocationMinor = $lateTazirPenaltyMinor;

            // 3. Fase 161 & 163: Mudharabah Nisbah Profit-Loss Sharing & BMT Empowerment
            $sahibulMaalNisbahBps = (int) ($params['sahibul_maal_nisbah_bps'] ?? 6000);
            $mudharibNisbahBps = (int) ($params['mudharib_nisbah_bps'] ?? 4000);
            if (($sahibulMaalNisbahBps + $mudharibNisbahBps) !== 10000) {
                throw new DomainException('Mudharabah nisbah split must sum to exactly 10000 basis points (100%)');
            }

            $ventureProfitLossMinor = (int) ($params['venture_profit_loss_minor'] ?? 50000000);
            $mudharibMisconduct = (string) ($params['mudharib_misconduct_finding'] ?? 'NONE');

            $sahibulMaalShareMinor = 0;
            $mudharibShareMinor = 0;
            $capitalLossAbsorbedByProviderMinor = 0;

            if ($ventureProfitLossMinor > 0) {
                $sahibulMaalShareMinor = intdiv($ventureProfitLossMinor * $sahibulMaalNisbahBps, 10000);
                $mudharibShareMinor = $ventureProfitLossMinor - $sahibulMaalShareMinor;
            } elseif ($ventureProfitLossMinor < 0) {
                if ($mudharibMisconduct === 'NONE') {
                    $capitalLossAbsorbedByProviderMinor = abs($ventureProfitLossMinor);
                } else {
                    $mudharibShareMinor = $ventureProfitLossMinor;
                }
            }

            // 4. Fase 162 & 164: Sukuk Ijarah Tangible Underlying Asset Backing Gate
            $sukukIssuanceMinor = (int) ($params['sukuk_issuance_minor'] ?? 500000000);
            $underlyingAssetMinor = (int) ($params['underlying_ijarah_asset_minor'] ?? 600000000);
            if ($underlyingAssetMinor < $sukukIssuanceMinor) {
                throw new DomainException("Sukuk Ijarah issuance rejected: underlying tangible asset ({$underlyingAssetMinor}) is below sukuk principal ({$sukukIssuanceMinor})");
            }

            // 5. Fase 163 & 165: Corporate Zakat (2.5% = 250 bps above Nisab) & Wakaf Perpetuity Lock
            $zakatableNetAssetsMinor = (int) ($params['zakatable_net_assets_minor'] ?? 1000000000);
            $nisabThresholdMinor = (int) ($params['nisab_threshold_minor'] ?? 120000000);
            $zakatObligationMinor = $zakatableNetAssetsMinor >= $nisabThresholdMinor
                ? intdiv($zakatableNetAssetsMinor * 250, 10000)
                : 0;

            $wakafCorpusPrincipalMinor = (int) ($params['wakaf_corpus_principal_minor'] ?? 1000000000);
            $wakafPrincipalEncroachmentMinor = (int) ($params['wakaf_principal_drawdown_minor'] ?? 0);
            if ($wakafPrincipalEncroachmentMinor > 0) {
                throw new DomainException('Wakaf perpetuity violation: principal wakaf corpus is immutable and cannot be drawn down');
            }

            $previousHash = (string) ($params['previous_hash'] ?? hash('sha256', 'GENESIS-WAVE3-SYARIAH'));
            $akadChainHash = hash('sha256', implode('|', [
                $akadCode,
                $sectorCode,
                (string) $murabahahSellingPriceMinor,
                (string) $charityPoolAllocationMinor,
                (string) $zakatObligationMinor,
                $previousHash,
            ]));

            if ($recognizedHalalMarginMinor > 0 && $this->ledger !== null) {
                $entriesBuilder = Posting::lines()
                    ->debit('wave3:syariah_murabahah_receivable:IDR', $recognizedHalalMarginMinor + $charityPoolAllocationMinor + $zakatObligationMinor)
                    ->credit('wave3:syariah_margin_revenue:IDR', $recognizedHalalMarginMinor);

                if ($charityPoolAllocationMinor > 0) {
                    $entriesBuilder = $entriesBuilder->credit('wave3:syariah_charity_pool:IDR', $charityPoolAllocationMinor);
                }
                if ($zakatObligationMinor > 0) {
                    $entriesBuilder = $entriesBuilder->credit('wave3:zakat_wakaf_escrow:IDR', $zakatObligationMinor);
                }

                $idempotencyKey = hash('sha256', "W3:SYR:AKAD:{$akadCode}:{$recognizedHalalMarginMinor}:{$zakatObligationMinor}");
                $this->ledger->post(new PostingDTO(
                    type: 'WAVE3_SYARIAH_FINANCE_POOL',
                    description: "Syariah Murabahah margin, charity routing & Zakat pool {$akadCode}",
                    idempotencyKey: $idempotencyKey,
                    entries: $entriesBuilder->toEntries('IDR'),
                    referenceType: 'WAVE3_SYARIAH_AKAD',
                    referenceId: $akadCode,
                ));
            }

            $result = [
                'akad_code' => $akadCode,
                'shariah_screening_status' => 'HALAL_CERTIFIED_DSN_COMPLIANT',
                'murabahah_selling_price_minor' => $murabahahSellingPriceMinor,
                'recognized_halal_margin_minor' => $recognizedHalalMarginMinor,
                'charity_pool_allocation_minor' => $charityPoolAllocationMinor,
                'sahibul_maal_share_minor' => $sahibulMaalShareMinor,
                'mudharib_share_minor' => $mudharibShareMinor,
                'capital_loss_absorbed_by_provider_minor' => $capitalLossAbsorbedByProviderMinor,
                'sukuk_issuance_minor' => $sukukIssuanceMinor,
                'zakat_obligation_minor' => $zakatObligationMinor,
                'wakaf_corpus_principal_minor' => $wakafCorpusPrincipalMinor,
                'akad_chain_hash' => $akadChainHash,
            ];

            $this->audit(
                action: 'core.wave3.syariah_finance_and_zakat_settled',
                newValues: [
                    'akad_code' => $akadCode,
                    'murabahah_selling_price_minor' => $murabahahSellingPriceMinor,
                    'zakat_obligation_minor' => $zakatObligationMinor,
                ],
                correlationId: "w3_syr_{$akadCode}",
                impactType: 'syariah_finance'
            );

            $this->outbox(
                eventType: 'Wave3SyariahFinanceSettled',
                payload: [
                    'akad_code' => $akadCode,
                    'zakat_obligation_minor' => $zakatObligationMinor,
                    'akad_chain_hash' => $akadChainHash,
                ],
                idempotencyKey: hash('sha256', "OUTBOX:W3:SYR:{$akadCode}")
            );

            return $result;
        });
    }
}

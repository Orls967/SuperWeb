<?php

declare(strict_types=1);

namespace Modules\TradeFinance\Application\Services\CrossBorder;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\DTOs\PostingEntryDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\TradeFinance\Domain\Models\CrossBorder\CbamCertificate;
use Modules\TradeFinance\Domain\Models\CrossBorder\CrossBorderEscrow;
use RuntimeException;

class CrossBorderClearingService
{
    public function __construct(
        protected ?LedgerService $ledgerService = null
    ) {
        $this->ledgerService = $ledgerService ?? app(LedgerService::class);
    }

    /**
     * 83.1 Deposit stablecoin into crossborder escrow with locked FX rate and unique BL
     */
    public function depositEscrow(
        int $importerId,
        int $exporterId,
        int $amountCents, // e.g. 100,000 cents = $1,000 USD
        int $lockedFxRateIdr, // e.g. 16,000 IDR/USD
        string $blNumber,
        string $blDocument
    ): CrossBorderEscrow {
        if ($amountCents <= 0 || $lockedFxRateIdr <= 0) {
            throw new InvalidArgumentException('Amount and locked FX rate must be positive integers');
        }

        return DB::transaction(function () use ($importerId, $exporterId, $amountCents, $lockedFxRateIdr, $blNumber, $blDocument) {
            $existing = CrossBorderEscrow::where('bl_number', $blNumber)->first();
            if ($existing) {
                throw new RuntimeException('Duplicate Bill of Lading (BL) number rejected');
            }

            // Deterministic code generation (A4 compliant)
            $entropy = hash('sha256', "{$importerId}:{$exporterId}:{$blNumber}:{$amountCents}");
            $escrowCode = 'CBE-'.strtoupper(substr($entropy, 0, 12));
            $blHash = hash('sha256', "{$blNumber}:{$blDocument}");
            $totalIdrEquivalent = (int) round(($amountCents / 100.0) * $lockedFxRateIdr);

            $escrow = CrossBorderEscrow::create([
                'escrow_code' => $escrowCode,
                'importer_id' => $importerId,
                'exporter_id' => $exporterId,
                'stablecoin_asset' => 'USDC',
                'amount_cents' => $amountCents,
                'locked_fx_rate_idr' => $lockedFxRateIdr,
                'bl_number' => $blNumber,
                'bl_document_hash' => $blHash,
                'status' => 'DEPOSITED',
            ]);

            // Post deposit to ledger: Debit Importer Wallet, Credit Cross-border Escrow Liability
            $this->ledgerService->post(new PostingDTO(
                type: 'CROSSBORDER_ESCROW_DEPOSIT',
                description: "Crossborder stablecoin escrow deposit for BL {$blNumber}",
                idempotencyKey: "CBE-DEP-{$escrow->escrow_code}",
                entries: [
                    PostingEntryDTO::forCode('tf:crossborder_escrow:IDR', 'IDR', $totalIdrEquivalent),
                    PostingEntryDTO::forCode('tf:importer_wallet:IDR', 'IDR', -$totalIdrEquivalent),
                ],
                referenceType: 'CROSSBORDER_ESCROW',
                referenceId: (string) $escrow->id,
            ));

            return $escrow;
        });
    }

    /**
     * 83.1 Release escrow when verified POD matches logistics hash chain
     */
    public function releaseEscrowOnDelivery(CrossBorderEscrow $escrow, string $verifiedPodHash): CrossBorderEscrow
    {
        return DB::transaction(function () use ($escrow, $verifiedPodHash) {
            $locked = CrossBorderEscrow::where('id', $escrow->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== 'DEPOSITED') {
                throw new RuntimeException("Escrow cannot be released from status {$locked->status}");
            }

            $locked->update([
                'pod_chain_hash' => $verifiedPodHash,
                'status' => 'RELEASED',
                'released_at' => now(),
            ]);

            $totalIdrEquivalent = (int) round(($locked->amount_cents / 100.0) * $locked->locked_fx_rate_idr);

            // Post release to ledger: Debit Crossborder Escrow Liability, Credit Exporter Receivable/Wallet
            $this->ledgerService->post(new PostingDTO(
                type: 'CROSSBORDER_ESCROW_RELEASE',
                description: "Crossborder escrow release upon verified POD for BL {$locked->bl_number}",
                idempotencyKey: "CBE-REL-{$locked->escrow_code}",
                entries: [
                    PostingEntryDTO::forCode('tf:exporter_settlement:IDR', 'IDR', $totalIdrEquivalent),
                    PostingEntryDTO::forCode('tf:crossborder_escrow:IDR', 'IDR', -$totalIdrEquivalent),
                ],
                referenceType: 'CROSSBORDER_ESCROW',
                referenceId: (string) $locked->id,
            ));

            return $locked;
        });
    }

    /**
     * 83.2 Anti-fraud: Put escrow on dispute hold within 24-hour dispute window
     */
    public function holdDispute(CrossBorderEscrow $escrow, string $reason): CrossBorderEscrow
    {
        return DB::transaction(function () use ($escrow, $reason) {
            $locked = CrossBorderEscrow::where('id', $escrow->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== 'DEPOSITED') {
                throw new RuntimeException("Only DEPOSITED escrows can enter dispute hold (current: {$locked->status})");
            }

            $locked->update([
                'status' => 'DISPUTED',
                'disputed_at' => now(),
                'dispute_reason' => $reason,
                'hold_until' => now()->addHours(24),
            ]);

            return $locked;
        });
    }

    /**
     * 83.2 Resolve dispute: either refund to importer or release hold
     */
    public function resolveDispute(CrossBorderEscrow $escrow, string $resolutionDecision): CrossBorderEscrow
    {
        return DB::transaction(function () use ($escrow, $resolutionDecision) {
            $locked = CrossBorderEscrow::where('id', $escrow->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== 'DISPUTED') {
                throw new RuntimeException('Escrow is not in DISPUTED status');
            }

            $totalIdrEquivalent = (int) round(($locked->amount_cents / 100.0) * $locked->locked_fx_rate_idr);

            if ($resolutionDecision === 'REFUND') {
                $locked->update([
                    'status' => 'REFUNDED',
                    'released_at' => now(),
                ]);

                // Refund ledger posting: reverse deposit
                $this->ledgerService->post(new PostingDTO(
                    type: 'CROSSBORDER_ESCROW_REFUND',
                    description: "Crossborder escrow refund for BL {$locked->bl_number} (dispute resolved)",
                    idempotencyKey: "CBE-REF-{$locked->escrow_code}",
                    entries: [
                        PostingEntryDTO::forCode('tf:importer_wallet:IDR', 'IDR', $totalIdrEquivalent),
                        PostingEntryDTO::forCode('tf:crossborder_escrow:IDR', 'IDR', -$totalIdrEquivalent),
                    ],
                    referenceType: 'CROSSBORDER_ESCROW',
                    referenceId: (string) $locked->id,
                ));
            } else {
                // Resume normal deposited state
                $locked->update([
                    'status' => 'DEPOSITED',
                    'hold_until' => null,
                ]);
            }

            return $locked;
        });
    }

    /**
     * 83.3 & 83.4 Calculate EU CBAM embedded carbon emissions, generate certificate and link carbon credits
     */
    public function issueCbamCertificate(
        string $containerNumber,
        string $originFactoryCode,
        string $commodityCode,
        float $netMassTons,
        float $embeddedEmissionsTco2,
        int $eurToIdrRate = 17_000,
        int $cbamPriceEurPerTon = 80,
        ?string $carbonCreditRef = null
    ): CbamCertificate {
        if ($netMassTons <= 0.0 || $embeddedEmissionsTco2 <= 0.0) {
            throw new InvalidArgumentException('Net mass and emissions must be positive numbers');
        }

        $existing = CbamCertificate::where('container_number', $containerNumber)->first();
        if ($existing) {
            return $existing;
        }

        // Deterministic code generation (A4 compliant)
        $certEntropy = hash('sha256', "{$containerNumber}:{$originFactoryCode}:{$commodityCode}:{$embeddedEmissionsTco2}");
        $certCode = 'CBAM-'.strtoupper(substr($certEntropy, 0, 12));
        $levyIdr = (int) round($embeddedEmissionsTco2 * $cbamPriceEurPerTon * $eurToIdrRate);
        $certHash = hash('sha256', "{$certCode}:{$containerNumber}:{$commodityCode}:{$embeddedEmissionsTco2}:{$levyIdr}");

        return CbamCertificate::create([
            'cert_code' => $certCode,
            'container_number' => $containerNumber,
            'origin_factory_code' => $originFactoryCode,
            'commodity_code' => $commodityCode,
            'net_mass_tons' => $netMassTons,
            'embedded_emissions_tco2' => $embeddedEmissionsTco2,
            'cbam_benchmark_factor' => 1.25,
            'cbam_levy_cost_idr' => $levyIdr,
            'certificate_hash' => $certHash,
            'status' => 'CERTIFIED',
            'carbon_credit_ref' => $carbonCreditRef,
            'export_margin_deduction_idr' => $levyIdr,
        ]);
    }

    /**
     * 83.4 Calculate impact of CBAM levy on export landed cost and margin
     *
     * @return array{base_margin_idr: int, cbam_levy_deduction_idr: int, net_margin_idr: int, margin_reduction_percent: float}
     */
    public function calculateExportMarginImpact(CbamCertificate $certificate, int $baseExportMarginIdr): array
    {
        $deduction = $certificate->cbam_levy_cost_idr;
        $netMargin = max(0, $baseExportMarginIdr - $deduction);
        $reductionPct = $baseExportMarginIdr > 0 ? round(($deduction / $baseExportMarginIdr) * 100, 2) : 0.0;

        return [
            'base_margin_idr' => $baseExportMarginIdr,
            'cbam_levy_deduction_idr' => $deduction,
            'net_margin_idr' => $netMargin,
            'margin_reduction_percent' => $reductionPct,
        ];
    }

    /**
     * 83.5 Dashboard summaries: Escrow corridor liquidity & CBAM compliance
     */
    public function getEscrowCorridorSummary(): array
    {
        $all = CrossBorderEscrow::all();
        $deposited = $all->where('status', 'DEPOSITED');
        $disputed = $all->where('status', 'DISPUTED');
        $released = $all->where('status', 'RELEASED');

        $totalActiveVolumeIdr = (int) $deposited->sum(fn ($e) => ($e->amount_cents / 100.0) * $e->locked_fx_rate_idr);
        $totalDisputedVolumeIdr = (int) $disputed->sum(fn ($e) => ($e->amount_cents / 100.0) * $e->locked_fx_rate_idr);

        return [
            'total_escrows' => $all->count(),
            'deposited_count' => $deposited->count(),
            'released_count' => $released->count(),
            'disputed_count' => $disputed->count(),
            'active_liquidity_idr' => $totalActiveVolumeIdr,
            'disputed_liquidity_idr' => $totalDisputedVolumeIdr,
            'settlement_success_rate' => $all->count() > 0 ? round(($released->count() / $all->count()) * 100, 1) : 100.0,
        ];
    }

    public function getCbamComplianceSummary(): array
    {
        $certs = CbamCertificate::all();
        $totalEmissions = (float) $certs->sum('embedded_emissions_tco2');
        $totalLevyIdr = (int) $certs->sum('cbam_levy_cost_idr');
        $creditLinkedCount = $certs->whereNotNull('carbon_credit_ref')->count();

        return [
            'total_certificates' => $certs->count(),
            'total_emissions_tco2' => $totalEmissions,
            'total_levy_liability_idr' => $totalLevyIdr,
            'carbon_credit_linked_count' => $creditLinkedCount,
            'offset_coverage_percent' => $certs->count() > 0 ? round(($creditLinkedCount / $certs->count()) * 100, 1) : 0.0,
        ];
    }
}

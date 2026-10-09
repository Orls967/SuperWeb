<?php

namespace Modules\TradeFinance\Application\Services\CrossBorder;

use Illuminate\Support\Facades\DB;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\DTOs\PostingEntryDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\TradeFinance\Domain\Models\CrossBorder\CbamCertificate;
use Modules\TradeFinance\Domain\Models\CrossBorder\CrossBorderEscrow;

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
        return DB::transaction(function () use ($importerId, $exporterId, $amountCents, $lockedFxRateIdr, $blNumber, $blDocument) {
            $existing = CrossBorderEscrow::where('bl_number', $blNumber)->first();
            if ($existing) {
                throw new \RuntimeException('Duplicate Bill of Lading (BL) number rejected');
            }

            $escrowCode = 'CBE-'.strtoupper(bin2hex(random_bytes(6)));
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
                throw new \RuntimeException("Escrow cannot be released from status {$locked->status}");
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
     * 83.3 & 83.4 Calculate EU CBAM embedded carbon emissions and generate certificate
     */
    public function issueCbamCertificate(
        string $containerNumber,
        string $originFactoryCode,
        string $commodityCode,
        float $netMassTons,
        float $embeddedEmissionsTco2,
        int $eurToIdrRate = 17_000,
        float $cbamPriceEurPerTon = 80.0
    ): CbamCertificate {
        $existing = CbamCertificate::where('container_number', $containerNumber)->first();
        if ($existing) {
            return $existing;
        }

        $certCode = 'CBAM-'.strtoupper(bin2hex(random_bytes(6)));
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
        ]);
    }
}

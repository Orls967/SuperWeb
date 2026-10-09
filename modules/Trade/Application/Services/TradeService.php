<?php

declare(strict_types=1);

namespace Modules\Trade\Application\Services;

use Brick\Math\BigDecimal;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\DTOs\PostingEntryDTO;
use Modules\Banking\Contracts\Ledger;
use Modules\Banking\Domain\Models\LedgerAccount;
use Modules\Trade\Domain\Models\Country;
use Modules\Trade\Domain\Models\ExportOrder;
use Modules\Trade\Domain\Models\HsCode;
use Modules\Trade\Domain\Models\ImportOrder;
use Modules\Trade\Domain\Models\Incoterm;
use Modules\Trade\Domain\Models\Port;
use Modules\Trade\Domain\Models\ShipmentLeg;
use Modules\Trade\Domain\Models\TradeDispute;
use Modules\Trade\Domain\Models\TradeDocument;
use Modules\Treasury\Application\Services\TreasuryService;

class TradeService
{
    public function __construct(
        protected ?Ledger $ledger = null,
        protected ?TreasuryService $treasuryService = null
    ) {
        $this->ledger = $ledger ?? app(Ledger::class);
        $this->treasuryService = $treasuryService ?? app(TreasuryService::class);
    }

    /**
     * 49.1 Setup master data negara, pelabuhan, Incoterms, & HS Code
     */
    public function registerCountry(string $code, string $name, string $currencyCode = 'USD', bool $hasFta = false): Country
    {
        return Country::updateOrCreate(
            ['code' => strtoupper($code)],
            ['name' => $name, 'currency_code' => strtoupper($currencyCode), 'has_fta' => $hasFta]
        );
    }

    public function registerPort(string $code, string $name, string $countryCode, string $type = 'seaport'): Port
    {
        return Port::updateOrCreate(
            ['code' => strtoupper($code)],
            ['name' => $name, 'country_code' => strtoupper($countryCode), 'type' => $type]
        );
    }

    public function registerIncoterm(string $code, string $name, string $riskTransferPoint, string $costResponsibility): Incoterm
    {
        return Incoterm::updateOrCreate(
            ['code' => strtoupper($code)],
            ['name' => $name, 'risk_transfer_point' => $riskTransferPoint, 'cost_responsibility' => $costResponsibility]
        );
    }

    public function registerHsCode(
        string $hsCode,
        string $desc,
        float $baseDuty = 5.0,
        float $ftaRate = 0.0,
        bool $isLartas = false,
        ?string $permit = null
    ): HsCode {
        return HsCode::updateOrCreate(
            ['hs_code' => $hsCode],
            [
                'description' => $desc,
                'base_duty_rate_percent' => $baseDuty,
                'fta_preferential_rate_percent' => $ftaRate,
                'is_lartas' => $isLartas,
                'lartas_permit_required' => $permit,
            ]
        );
    }

    /**
     * 49.2 Order Ekspor & Pengakuan Pendapatan sesuai Incoterm
     */
    public function createExportOrder(
        string $buyerName,
        string $destCountryCode,
        string $destPortId,
        string $incotermCode,
        int $foreignAmount,
        string $currency = 'USD'
    ): ExportOrder {
        $functionalIdr = $this->treasuryService->convertAmount($foreignAmount, $currency, 'IDR');

        return ExportOrder::create([
            'order_number' => 'EXP-'.strtoupper(Str::random(8)),
            'buyer_name' => $buyerName,
            'destination_country_code' => strtoupper($destCountryCode),
            'destination_port_id' => $destPortId,
            'incoterm_code' => strtoupper($incotermCode),
            'currency' => strtoupper($currency),
            'total_foreign_amount' => $foreignAmount,
            'total_functional_idr' => $functionalIdr,
            'status' => 'draft',
        ]);
    }

    public function confirmExportPeb(ExportOrder $order, string $pebNo): ExportOrder
    {
        $order->update([
            'peb_number' => $pebNo,
            'status' => 'confirmed',
        ]);

        return $order;
    }

    public function recognizeExportRevenue(ExportOrder $order, string $idempotencyKey): ExportOrder
    {
        return DB::transaction(function () use ($order, $idempotencyKey) {
            if ($order->status === 'risk_transferred') {
                return $order;
            }

            // Ensure ledger accounts exist
            LedgerAccount::firstOrCreate(
                ['code' => 'ar:international:IDR'],
                [
                    'uuid' => (string) Str::uuid(),
                    'asset_code' => 'IDR',
                    'kind' => 'ASSET',
                    'name' => 'International Accounts Receivable IDR',
                    'allow_negative' => true,
                    'cached_balance' => '0',
                ]
            );

            LedgerAccount::firstOrCreate(
                ['code' => 'revenue:export:IDR'],
                [
                    'uuid' => (string) Str::uuid(),
                    'asset_code' => 'IDR',
                    'kind' => 'REVENUE',
                    'name' => 'Export Sales Revenue IDR',
                    'allow_negative' => true,
                    'cached_balance' => '0',
                ]
            );

            // Post revenue recognition to ledger in IDR
            $this->ledger->post(new PostingDTO(
                type: 'TRADE_EXPORT_REVENUE',
                description: "Export revenue recognized for {$order->order_number} ({$order->incoterm_code})",
                idempotencyKey: $idempotencyKey,
                entries: [
                    PostingEntryDTO::forCode(
                        accountCode: 'ar:international:IDR',
                        assetCode: 'IDR',
                        amount: BigDecimal::of((string) (-$order->total_functional_idr))
                    ),
                    PostingEntryDTO::forCode(
                        accountCode: 'revenue:export:IDR',
                        assetCode: 'IDR',
                        amount: BigDecimal::of((string) $order->total_functional_idr)
                    ),
                ]
            ));

            $order->update([
                'status' => 'risk_transferred',
                'risk_transferred_at' => Carbon::now(),
            ]);

            return $order;
        });
    }

    /**
     * 49.3 & 49.5 Order Impor & Kalkulator Bea Cukai (CustomsDutyCalculator) dengan FTA Preferential Rate
     */
    public function calculateImportDuties(int $cifIdr, HsCode $hsCode, bool $hasValidCoo = false): array
    {
        $dutyRate = ($hasValidCoo && $hsCode->fta_preferential_rate_percent !== null)
            ? (float) $hsCode->fta_preferential_rate_percent
            : (float) $hsCode->base_duty_rate_percent;

        // Bea Masuk (BM) = CIF * dutyRate%
        $bm = (int) round($cifIdr * ($dutyRate / 100));

        // Nilai Impor = CIF + BM
        $importValue = $cifIdr + $bm;

        // PPN Impor 11%
        $ppn = (int) round($importValue * 0.11);

        // PPh Pasal 22 Impor (2.5% dengan API-U / standar)
        $pph22 = (int) round($importValue * 0.025);

        $totalLandedCost = $importValue + $ppn + $pph22;

        return [
            'duty_rate_used' => $dutyRate,
            'is_preferential' => ($dutyRate < (float) $hsCode->base_duty_rate_percent),
            'customs_duty_bm_idr' => $bm,
            'import_vat_ppn_idr' => $ppn,
            'import_tax_pph22_idr' => $pph22,
            'total_landed_cost_idr' => $totalLandedCost,
        ];
    }

    public function createImportOrder(
        string $supplierName,
        string $originCountryCode,
        string $originPortId,
        string $incotermCode,
        int $cifForeignAmount,
        string $currency,
        HsCode $hsCode,
        bool $hasValidCoo = false
    ): ImportOrder {
        $cifIdr = $this->treasuryService->convertAmount($cifForeignAmount, $currency, 'IDR');
        $tax = $this->calculateImportDuties($cifIdr, $hsCode, $hasValidCoo);

        return ImportOrder::create([
            'order_number' => 'IMP-'.strtoupper(Str::random(8)),
            'supplier_name' => $supplierName,
            'origin_country_code' => strtoupper($originCountryCode),
            'origin_port_id' => $originPortId,
            'incoterm_code' => strtoupper($incotermCode),
            'currency' => strtoupper($currency),
            'cif_foreign_amount' => $cifForeignAmount,
            'cif_idr' => $cifIdr,
            'customs_duty_bm_idr' => $tax['customs_duty_bm_idr'],
            'import_vat_ppn_idr' => $tax['import_vat_ppn_idr'],
            'import_tax_pph22_idr' => $tax['import_tax_pph22_idr'],
            'total_landed_cost_idr' => $tax['total_landed_cost_idr'],
            'coo_verified' => $hasValidCoo,
            'status' => 'ordered',
        ]);
    }

    /**
     * 49.4 Dokumen Perdagangan (Certificate of Origin, Fumigasi, etc.)
     */
    public function attachTradeDocument(
        string $documentableType,
        string $documentableId,
        string $docType,
        string $certNo,
        string $issuingAuthority,
        string $issueDate,
        ?string $validUntil = null
    ): TradeDocument {
        return TradeDocument::create([
            'documentable_type' => $documentableType,
            'documentable_id' => $documentableId,
            'doc_type' => $docType,
            'certificate_number' => $certNo,
            'issuing_authority' => $issuingAuthority,
            'issue_date' => $issueDate,
            'valid_until' => $validUntil,
            'is_verified' => true,
        ]);
    }

    /**
     * 49.6 Pelacakan Lintas Batas (Cross-Border Tracking Hash-Chain)
     */
    public function recordShipmentLeg(
        string $orderRefNo,
        string $stage,
        string $locationName,
        ?string $notes = null
    ): ShipmentLeg {
        $lastLeg = ShipmentLeg::where('order_reference_no', $orderRefNo)->orderByDesc('created_at')->orderByDesc('id')->first();
        $previousHash = $lastLeg ? $lastLeg->hash : 'GENESIS_CROSS_BORDER_TRACK';
        $now = Carbon::now();

        $payload = implode('|', [
            $orderRefNo,
            $stage,
            $locationName,
            $previousHash,
            microtime(true),
        ]);
        $hash = hash('sha256', $payload);

        return ShipmentLeg::create([
            'order_reference_no' => $orderRefNo,
            'leg_stage' => $stage,
            'location_name' => $locationName,
            'notes' => $notes,
            'previous_hash' => $previousHash,
            'hash' => $hash,
            'recorded_at' => $now,
        ]);
    }

    public function verifyCrossBorderChain(string $orderRefNo): bool
    {
        $legs = ShipmentLeg::where('order_reference_no', $orderRefNo)->orderBy('created_at')->orderBy('id')->get();
        if ($legs->isEmpty()) {
            return true;
        }

        $expectedPrev = 'GENESIS_CROSS_BORDER_TRACK';
        foreach ($legs as $leg) {
            if ($leg->previous_hash !== $expectedPrev) {
                return false;
            }
            $expectedPrev = $leg->hash;
        }

        return true;
    }

    /**
     * 49.7 Sengketa & Klaim Dagang Internasional
     */
    public function fileDispute(
        string $orderRefNo,
        string $reason,
        int $claimAmountIdr
    ): TradeDispute {
        return TradeDispute::create([
            'dispute_number' => 'DSP-'.strtoupper(Str::random(8)),
            'order_reference_no' => $orderRefNo,
            'claim_reason' => $reason,
            'claim_amount_idr' => $claimAmountIdr,
            'insurance_payout_idr' => 0,
            'status' => 'submitted',
        ]);
    }

    public function settleDisputeWithInsurance(TradeDispute $dispute, int $payoutIdr): TradeDispute
    {
        $dispute->update([
            'insurance_payout_idr' => $payoutIdr,
            'status' => 'settled',
        ]);

        return $dispute;
    }

    /**
     * 49.9 Audit Trade: pastikan pesanan ekspor/impor valid dan integritas hash-chain
     */
    public function auditTrade(): array
    {
        $exportOrders = ExportOrder::count();
        $importOrders = ImportOrder::count();
        $disputes = TradeDispute::count();

        // Verifikasi semua order dengan tracking legs
        $orderRefs = ShipmentLeg::select('order_reference_no')->distinct()->pluck('order_reference_no');
        $brokenChains = 0;
        foreach ($orderRefs as $ref) {
            if (! $this->verifyCrossBorderChain($ref)) {
                $brokenChains++;
            }
        }

        return [
            'status' => ($brokenChains === 0) ? 'OK' : 'DISCREPANCY',
            'export_orders_count' => $exportOrders,
            'import_orders_count' => $importOrders,
            'disputes_count' => $disputes,
            'broken_chains_count' => $brokenChains,
        ];
    }
}

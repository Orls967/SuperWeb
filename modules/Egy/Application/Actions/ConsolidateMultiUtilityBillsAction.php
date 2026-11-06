<?php

declare(strict_types=1);

namespace Modules\Egy\Application\Actions;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Banking\Domain\Enums\AccountKind;
use Modules\Banking\Support\Posting;
use Modules\Core\Contracts\DocumentNumberingInterface;
use Modules\Egy\Domain\Models\EgyParameter;
use Modules\Egy\Domain\Models\UtilityConsolidatedInvoice;
use Modules\Egy\Domain\Models\UtilityInvoiceLine;
use Modules\Egy\Ledger\EgyAccounts;
use Modules\Egy\Ledger\EgyTransactionTypes;
use Modules\Shared\Application\BaseAction;

final class ConsolidateMultiUtilityBillsAction extends BaseAction
{
    public function __construct(
        private readonly DocumentNumberingInterface $numbering,
        private readonly LedgerService $ledgerService
    ) {}

    public function execute(
        int $propertyId,
        string $periodMonth,
        int $customerPartyId,
        int $waterVolumeM3,
        int $coolingKwhTh,
        string $idempotencyKey
    ): UtilityConsolidatedInvoice {
        if ($propertyId <= 0 || $customerPartyId <= 0) {
            throw new InvalidArgumentException('ID Properti dan Pelanggan harus positif');
        }
        if ($waterVolumeM3 < 0 || $coolingKwhTh < 0) {
            throw new InvalidArgumentException('Volume air dan kuantitas cooling tidak boleh negatif');
        }
        if (trim($idempotencyKey) === '') {
            throw new InvalidArgumentException('Idempotency key wajib diisi');
        }

        return DB::transaction(function () use (
            $propertyId,
            $periodMonth,
            $customerPartyId,
            $waterVolumeM3,
            $coolingKwhTh,
            $idempotencyKey
        ) {
            $existing = UtilityConsolidatedInvoice::where('billing_period', $periodMonth)
                ->where('property_id', $propertyId)
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            // Hitung tarif air bertingkat (S1)
            $waterTariffParam = EgyParameter::where('parameter_key', 'WATER_TARIFF_TIERS')
                ->where('status', 'active')
                ->first();

            $t1Max = 10;
            $t1Rate = 5000;
            $t2Max = 20;
            $t2Rate = 7500;
            $t3Rate = 10000;

            if ($waterTariffParam !== null && is_array($waterTariffParam->value_payload)) {
                $payload = $waterTariffParam->value_payload;
                $t1Max = (int) ($payload['tier1_max_m3'] ?? 10);
                $t1Rate = (int) ($payload['tier1_rate_idr'] ?? 5000);
                $t2Max = (int) ($payload['tier2_max_m3'] ?? 20);
                $t2Rate = (int) ($payload['tier2_rate_idr'] ?? 7500);
                $t3Rate = (int) ($payload['tier3_rate_idr'] ?? 10000);
            }

            $waterChargeIdr = 0;
            $remWater = $waterVolumeM3;

            if ($remWater > 0) {
                $t1Vol = min($remWater, $t1Max);
                $waterChargeIdr += $t1Vol * $t1Rate;
                $remWater -= $t1Vol;
            }
            if ($remWater > 0) {
                $t2Vol = min($remWater, $t2Max - $t1Max);
                $waterChargeIdr += $t2Vol * $t2Rate;
                $remWater -= $t2Vol;
            }
            if ($remWater > 0) {
                $waterChargeIdr += $remWater * $t3Rate;
            }

            // Hitung tarif cooling termal (S1)
            $coolingParam = EgyParameter::where('parameter_key', 'DISTRICT_COOLING_TARIFF')
                ->where('status', 'active')
                ->first();
            $coolingRate = 1200;
            if ($coolingParam !== null && is_array($coolingParam->value_payload)) {
                $coolingRate = (int) ($coolingParam->value_payload['rate_idr_per_kwh_th'] ?? 1200);
            }
            $coolingChargeIdr = $coolingKwhTh * $coolingRate;

            $totalAmountIdr = $waterChargeIdr + $coolingChargeIdr;

            $invNumber = 'INV-CONS-'.str_replace('-', '', $periodMonth).'-'.str_pad((string) $propertyId, 4, '0', STR_PAD_LEFT);

            $invoice = UtilityConsolidatedInvoice::create([
                'invoice_number' => $invNumber,
                'property_id' => $propertyId,
                'customer_party_id' => $customerPartyId,
                'period' => $periodMonth,
                'billing_period' => $periodMonth,
                'water_charge_minor' => $waterChargeIdr,
                'total_amount_idr' => $totalAmountIdr,
                'total_consolidated_minor' => $totalAmountIdr,
                'status' => 'issued',
                'idempotency_key' => $idempotencyKey,
            ]);

            // Line items
            UtilityInvoiceLine::create([
                'invoice_id' => $invoice->id,
                'window_type' => 'WATER',
                'wh_consumed' => $waterVolumeM3 * 1000,
                'rate_per_kwh_minor' => $waterVolumeM3 > 0 ? (int) intdiv($waterChargeIdr, $waterVolumeM3) : 0,
                'line_total_minor' => $waterChargeIdr,
                'subtotal_minor' => $waterChargeIdr,
            ]);

            UtilityInvoiceLine::create([
                'invoice_id' => $invoice->id,
                'window_type' => 'COOLING',
                'wh_consumed' => $coolingKwhTh * 1000,
                'rate_per_kwh_minor' => $coolingRate,
                'line_total_minor' => $coolingChargeIdr,
                'subtotal_minor' => $coolingChargeIdr,
            ]);

            // Posting Ledger Standar SuperWeb
            $customerArAccount = EgyAccounts::getOrCreateCustomerAr((string) $customerPartyId);
            EgyAccounts::ensureAccount('egy:water_revenue:IDR', 'Water Revenue', AccountKind::REVENUE, 'IDR', false);
            EgyAccounts::ensureAccount('egy:thermal_revenue:IDR', 'Thermal Revenue', AccountKind::REVENUE, 'IDR', false);

            $postingLines = Posting::lines();
            if ($waterChargeIdr > 0) {
                $postingLines
                    ->debit($customerArAccount->code, $waterChargeIdr)
                    ->credit('egy:water_revenue:IDR', $waterChargeIdr);
            }
            if ($coolingChargeIdr > 0) {
                $postingLines
                    ->debit($customerArAccount->code, $coolingChargeIdr)
                    ->credit('egy:thermal_revenue:IDR', $coolingChargeIdr);
            }

            $tx = $this->ledgerService->post(new PostingDTO(
                type: EgyTransactionTypes::EGY_GRID_BILLING->value,
                description: "Consolidated multi-utility bill {$invoice->invoice_number}",
                idempotencyKey: "POST-{$idempotencyKey}",
                entries: $postingLines->toEntries('IDR'),
                referenceType: 'UTILITY_CONSOLIDATED_INVOICE',
                referenceId: (string) $invoice->id
            ));

            $invoice->update(['ledger_transaction_id' => (string) $tx->id]);

            return $invoice;
        });
    }
}

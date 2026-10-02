<?php

declare(strict_types=1);

namespace Modules\Logistics\Application\Services;

use Illuminate\Support\Facades\DB;
use Modules\Banking\Domain\Models\LedgerAccount;
use Modules\Logistics\Domain\Enums\PaymentTerms;
use Modules\Logistics\Domain\Enums\ShipmentStatus;
use Modules\Logistics\Domain\Models\Carrier;
use Modules\Logistics\Domain\Models\Claim;
use Modules\Logistics\Domain\Models\CodCollection;
use Modules\Logistics\Domain\Models\ContainerDwell;
use Modules\Logistics\Domain\Models\CustomsDeclaration;
use Modules\Logistics\Domain\Models\FuelLog;
use Modules\Logistics\Domain\Models\LogisticsInvoice;
use Modules\Logistics\Domain\Models\Shipment;
use Modules\Logistics\Domain\Models\ShipmentLeg;

/**
 * Audit penagihan logistik: mencocokkan dokumen bisnis (resi, invoice, D&D, bea cukai, COD, carrier, klaim, BBM)
 * dengan saldo buku besar. Konvensi tanda ledger: kredit positif, debit negatif.
 */
class BillingAuditor
{
    /**
     * @return array<int, array{key: string, label: string, items: int, document: int, ledger: int, ok: bool, detail: string|null}>
     */
    public function run(): array
    {
        return [
            $this->unearnedFreight(),
            $this->deliveredRevenue(),
            $this->freightRevenue(),
            $this->freightInvoices(),
            $this->receivables(),
            $this->demurrageRevenue(),
            $this->demurrageInvoices(),
            $this->customsDuty(),
            $this->codPayable(),
            $this->codFeeRevenue(),
            $this->codCash(),
            $this->carrierCost(),
            $this->carrierPayable(),
            $this->claimsExpense(),
            $this->fuelExpense(),
        ];
    }

    /** @param array<int, array{ok: bool}> $results */
    public function passed(array $results): bool
    {
        foreach ($results as $r) {
            if (! $r['ok']) {
                return false;
            }
        }

        return true;
    }

    private function balance(string $code): int
    {
        return (int) (LedgerAccount::where('code', $code)->where('asset_code', 'IDR')->value('cached_balance') ?? 0);
    }

    private function balanceLike(string $prefix): int
    {
        return (int) LedgerAccount::where('code', 'like', $prefix.'%')->where('asset_code', 'IDR')->get()->sum(fn ($a) => (int) $a->cached_balance);
    }

    /** @return array{key: string, label: string, items: int, document: int, ledger: int, ok: bool, detail: string|null} */
    private function result(string $key, string $label, int $items, int $document, int $ledger, ?string $detail = null): array
    {
        return ['key' => $key, 'label' => $label, 'items' => $items, 'document' => $document, 'ledger' => $ledger, 'ok' => $document === $ledger && $detail === null, 'detail' => $detail ?? ($document === $ledger ? null : 'Selisih Rp '.number_format($ledger - $document, 0, ',', '.'))];
    }

    private function unearnedFreight(): array
    {
        $query = Shipment::where('payment_terms', PaymentTerms::Prepaid)->whereNotIn('status', [
            ShipmentStatus::Draft->value, ShipmentStatus::Delivered->value, ShipmentStatus::Cancelled->value,
        ]);

        return $this->result('unearned', 'Unearned freight vs resi prabayar belum Delivered', (clone $query)->count(), (int) $query->sum('total_amount_idr'), $this->balance(LogisticsLedger::UNEARNED_FREIGHT));
    }

    private function deliveredRevenue(): array
    {
        $delivered = Shipment::where('status', ShipmentStatus::Delivered->value);
        $unrecognized = (clone $delivered)->whereNull('revenue_recognized_at')->count();
        $prematurely = Shipment::where('status', '!=', ShipmentStatus::Delivered->value)->whereNotNull('revenue_recognized_at')->count();
        $deliveredTotal = (int) (clone $delivered)->sum('total_amount_idr');
        $recognizedTotal = (int) (clone $delivered)->whereNotNull('revenue_recognized_at')->sum('total_amount_idr');

        $detail = ($unrecognized + $prematurely) > 0 ? "{$unrecognized} resi Delivered belum diakui pendapatannya; {$prematurely} resi non-Delivered sudah diakui." : null;

        return $this->result('delivered_revenue', 'Resi Delivered vs pengakuan pendapatan', $delivered->count(), $deliveredTotal, $recognizedTotal, $detail);
    }

    private function freightRevenue(): array
    {
        $recognized = Shipment::whereNotNull('revenue_recognized_at');
        $fees = Shipment::where('status', ShipmentStatus::Cancelled->value)->where('cancellation_fee_idr', '>', 0);

        return $this->result(
            'freight_revenue',
            'Pendapatan freight (Delivered + biaya batal) vs ledger',
            (clone $recognized)->count() + (clone $fees)->count(),
            (int) $recognized->sum('total_amount_idr') + (int) $fees->sum('cancellation_fee_idr'),
            $this->balance(LogisticsLedger::FREIGHT_REVENUE)
        );
    }

    private function freightInvoices(): array
    {
        $bad = 0;
        $invoiceTotal = 0;
        $shipmentTotal = 0;
        $invoices = LogisticsInvoice::where('kind', 'freight')->withSum('shipments as shipments_total', 'total_amount_idr')->get();
        foreach ($invoices as $invoice) {
            $invoiceTotal += (int) $invoice->total_amount_idr;
            $shipmentTotal += (int) $invoice->shipments_total;
            $bad += (int) $invoice->total_amount_idr === (int) $invoice->shipments_total ? 0 : 1;
        }

        return $this->result('freight_invoices', 'Invoice freight vs jumlah resi tertagih', $invoices->count(), $invoiceTotal, $shipmentTotal, $bad > 0 ? "{$bad} invoice tidak sama dengan total resinya." : null);
    }

    private function receivables(): array
    {
        $expected = [];
        $add = function (int $shipperId, int $amount) use (&$expected) {
            $expected[$shipperId] = ($expected[$shipperId] ?? 0) + $amount;
        };

        Shipment::where('payment_terms', PaymentTerms::Postpaid)->whereNotNull('revenue_recognized_at')
            ->selectRaw('shipper_id, sum(total_amount_idr) as total')->groupBy('shipper_id')->get()->each(fn ($r) => $add((int) $r->shipper_id, -(int) $r->total));
        ContainerDwell::selectRaw('shipper_id, sum(accrued_amount_idr) as total')->groupBy('shipper_id')->get()->each(fn ($r) => $add((int) $r->shipper_id, -(int) $r->total));
        LogisticsInvoice::where('status', 'paid')->selectRaw('shipper_id, sum(paid_amount_idr) as total')->groupBy('shipper_id')->get()->each(fn ($r) => $add((int) $r->shipper_id, (int) $r->total));

        $mismatches = [];
        $ledgerTotal = 0;
        $docTotal = 0;
        $ledgerByShipper = LedgerAccount::where('code', 'like', 'lgx:ar:%')->get()->mapWithKeys(fn ($a) => [(int) substr($a->code, 7) => (int) $a->cached_balance]);

        foreach ($ledgerByShipper->keys()->merge(array_keys($expected))->unique() as $shipperId) {
            $doc = $expected[$shipperId] ?? 0;
            $led = $ledgerByShipper[$shipperId] ?? 0;
            $docTotal += $doc;
            $ledgerTotal += $led;
            if ($doc !== $led) {
                $mismatches[] = "shipper #{$shipperId} (dokumen {$doc}, ledger {$led})";
            }
        }

        return $this->result('receivables', 'Piutang shipper (resi pascabayar + D&D - invoice dibayar) vs ledger AR', count($ledgerByShipper), $docTotal, $ledgerTotal, $mismatches ? 'Tidak cocok: '.implode('; ', $mismatches) : null);
    }

    private function demurrageRevenue(): array
    {
        return $this->result('dd_revenue', 'Akrual D&D vs pendapatan D&D', ContainerDwell::count(), (int) ContainerDwell::sum('accrued_amount_idr'), $this->balance(LogisticsLedger::DD_REVENUE));
    }

    private function demurrageInvoices(): array
    {
        $bad = 0;
        $invoiceTotal = 0;
        $dwellTotal = 0;
        $invoices = LogisticsInvoice::where('kind', 'dd')->get();
        foreach ($invoices as $invoice) {
            $sum = (int) ContainerDwell::where('invoice_id', $invoice->id)->sum('accrued_amount_idr');
            $invoiceTotal += (int) $invoice->total_amount_idr;
            $dwellTotal += $sum;
            $bad += (int) $invoice->total_amount_idr === $sum ? 0 : 1;
        }

        return $this->result('dd_invoices', 'Invoice D&D vs hitungan kontainer tertagih', $invoices->count(), $invoiceTotal, $dwellTotal, $bad > 0 ? "{$bad} invoice D&D tidak sama dengan total hitungannya." : null);
    }

    private function customsDuty(): array
    {
        $paid = CustomsDeclaration::whereNotNull('paid_at');

        return $this->result('customs', 'Bea cukai dibayar vs titipan bea cukai', (clone $paid)->count(), (int) $paid->sum('total_duty_idr'), $this->balance(LogisticsLedger::CUSTOMS_DUTY_PAYABLE));
    }

    private function codPayable(): array
    {
        $open = CodCollection::whereIn('status', [CodCollection::STATUS_COLLECTED, CodCollection::STATUS_DEPOSITED]);

        return $this->result('cod_payable', 'COD belum dicairkan vs titipan COD shipper', CodCollection::count(), (int) $open->sum('amount_idr'), $this->balanceLike('lgx:cod_payable:'));
    }

    private function codFeeRevenue(): array
    {
        $settled = CodCollection::where('status', CodCollection::STATUS_SETTLED);

        return $this->result('cod_fee', 'Fee COD tercairkan vs pendapatan fee COD', (clone $settled)->count(), (int) $settled->sum('fee_idr'), $this->balance(LogisticsLedger::COD_FEE_REVENUE));
    }

    private function codCash(): array
    {
        $driverDoc = -(int) CodCollection::where('status', CodCollection::STATUS_COLLECTED)->sum('amount_idr');
        $hubDoc = -(int) CodCollection::whereIn('status', [CodCollection::STATUS_DEPOSITED, CodCollection::STATUS_SETTLED])->sum('amount_idr');

        return $this->result('cod_cash', 'Kas COD (di driver + di hub) vs ledger', CodCollection::count(), $driverDoc + $hubDoc, $this->balanceLike('lgx:cod_cash:'));
    }

    private function carrierCost(): array
    {
        $accrued = ShipmentLeg::whereNotNull('cost_accrued_at');

        return $this->result('carrier_cost', 'Biaya carrier diakrual vs beban carrier', (clone $accrued)->count(), -(int) $accrued->sum('carrier_cost_idr'), $this->balance(LogisticsLedger::CARRIER_COST));
    }

    private function carrierPayable(): array
    {
        $unpaid = (int) ShipmentLeg::whereNotNull('cost_accrued_at')->whereNull('carrier_payment_id')->sum('carrier_cost_idr');
        $badPayments = 0;
        foreach (DB::table('lgx_carrier_payments')->get() as $payment) {
            $badPayments += (int) $payment->amount_idr === (int) ShipmentLeg::where('carrier_payment_id', $payment->id)->sum('carrier_cost_idr') ? 0 : 1;
        }

        return $this->result('carrier_payable', 'Utang carrier belum dibayar vs ledger (+ pembayaran vs leg)', Carrier::count(), $unpaid, $this->balanceLike('lgx:carrier_payable:'), $badPayments > 0 ? "{$badPayments} pembayaran carrier tidak sama dengan leg yang dilunasi." : null);
    }

    private function claimsExpense(): array
    {
        $paid = Claim::where('status', Claim::STATUS_PAID);

        return $this->result('claims', 'Klaim dibayar vs beban klaim', (clone $paid)->count(), -(int) $paid->sum('paid_amount_idr'), $this->balance(LogisticsLedger::CLAIMS_EXPENSE));
    }

    private function fuelExpense(): array
    {
        return $this->result('fuel', 'Catatan BBM vs beban BBM', FuelLog::count(), -(int) FuelLog::sum('total_cost_idr'), $this->balance(LogisticsLedger::FUEL_EXPENSE));
    }
}

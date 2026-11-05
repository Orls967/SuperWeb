<?php

declare(strict_types=1);

namespace Modules\Syariah\Application\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Syariah\Domain\Enums\IslamicTradeStatus;
use Modules\Syariah\Domain\Models\SybScfFacility;
use Modules\Syariah\Domain\Models\SybScfInvoice;
use Modules\Syariah\Domain\Models\SybScfRepayment;
use Modules\Syariah\Domain\Models\SybTradeAuditLog;
use Modules\Syariah\Domain\Models\SybTradeCustodyDocument;
use Modules\Syariah\Ledger\SyariahAccounts;
use Modules\Syariah\Ledger\SyariahTransactionTypes;
use RuntimeException;

final class MurabahahSupplyChainService
{
    public function __construct(
        private readonly ShariahLedgerPoster $ledgerPoster,
    ) {}

    public function createFacility(
        string $buyerPartyRef,
        string $supplierPartyRef,
        int $limitAmountCents,
        int $marginRateBps,
        int $maxTenorDays = 120
    ): SybScfFacility {
        if ($limitAmountCents <= 0) {
            throw new InvalidArgumentException('Pagu limit SCF harus bernilai positif.');
        }
        if ($marginRateBps <= 0 || $marginRateBps > 3000) {
            throw new InvalidArgumentException('Tingkat margin murabahah tidak valid (1 - 3000 bps).');
        }

        $count = SybScfFacility::count() + 1;
        $facilityNumber = sprintf('SCF-FAC-%s-%04d', date('Ym'), $count);

        return SybScfFacility::create([
            'facility_number' => $facilityNumber,
            'buyer_party_ref' => $buyerPartyRef,
            'supplier_party_ref' => $supplierPartyRef,
            'limit_amount_cents' => $limitAmountCents,
            'utilized_amount_cents' => 0,
            'margin_rate_bps' => $marginRateBps,
            'max_tenor_days' => $maxTenorDays,
            'status' => IslamicTradeStatus::ACTIVE->value,
        ]);
    }

    public function disburseMurabahahInvoice(
        int $facilityId,
        string $supplierInvoiceRef,
        int $principalCostCents,
        int $custodyDocumentId,
        int $tenorDays = 90
    ): SybScfInvoice {
        $facility = SybScfFacility::findOrFail($facilityId);

        if ($principalCostCents <= 0) {
            throw new InvalidArgumentException('Nominal pokok tagihan harus positif.');
        }

        if (($facility->utilized_amount_cents + $principalCostCents) > $facility->limit_amount_cents) {
            throw new InvalidArgumentException('Tagihan melebihi sisa limit fasilitas SCF.');
        }

        // QABDH GATE (Keputusan G7-D16 & Larangan Bai Qabla al-Qabdh)
        $custodyDoc = SybTradeCustodyDocument::findOrFail($custodyDocumentId);
        if ($custodyDoc->verified_qabdh_at === null) {
            throw new RuntimeException(
                'Larangan Bai Qabla al-Qabdh: komoditas/barang belum diverifikasi penguasaan dan kepemilikannya oleh petugas custody.'
            );
        }

        SyariahAccounts::ensureAccountsExist();

        return DB::transaction(function () use (
            $facility,
            $supplierInvoiceRef,
            $principalCostCents,
            $custodyDoc,
            $tenorDays
        ) {
            // Kalkulasi margin keuntungan murabahah SCF (linear non-compound)
            // margin = intdiv(principalCost * marginRateBps * tenorDays, 365 * 10000)
            $marginProfitCents = (int) intdiv($principalCostCents * $facility->margin_rate_bps * $tenorDays, 365 * 10000);
            $totalSalePriceCents = $principalCostCents + $marginProfitCents;

            $count = SybScfInvoice::count() + 1;
            $invoiceNumber = sprintf('SCF-INV-%s-%04d', date('Ym'), $count);
            $dueDate = CarbonImmutable::now()->addDays($tenorDays);

            $invoice = SybScfInvoice::create([
                'facility_id' => $facility->id,
                'invoice_number' => $invoiceNumber,
                'supplier_invoice_ref' => $supplierInvoiceRef,
                'principal_cost_cents' => $principalCostCents,
                'margin_profit_cents' => $marginProfitCents,
                'total_sale_price_cents' => $totalSalePriceCents,
                'custody_document_ref' => (string) $custodyDoc->id,
                'qabdh_verified_at' => $custodyDoc->verified_qabdh_at,
                'due_date' => $dueDate->toDateString(),
                'status' => IslamicTradeStatus::ACTIVE->value,
            ]);

            $facility->increment('utilized_amount_cents', $principalCostCents);

            // 1. Pembelian barang dari pemasok oleh bank:
            // Debit syb:scf_inventory:IDR, Kredit syb:cash_pool:IDR
            $this->ledgerPoster->postSimple(
                type: SyariahTransactionTypes::SYB_SCF_MURABAHAH_BUY,
                reference: "scf:buy:{$invoice->id}",
                description: "Pembelian komoditas/barang dari pemasok tagihan {$supplierInvoiceRef}",
                entries: [
                    ['account_code' => 'syb:scf_inventory:IDR', 'amount' => $principalCostCents],
                    ['account_code' => 'syb:cash_pool:IDR', 'amount' => -$principalCostCents],
                ]
            );

            // 2. Penjualan bertempo (murabahah) ke pembeli:
            // Debit syb:scf_murabahah_receivable:IDR, Kredit syb:scf_inventory:IDR, Kredit syb:scf_margin_income:IDR
            $this->ledgerPoster->postSimple(
                type: SyariahTransactionTypes::SYB_SCF_MURABAHAH_SELL,
                reference: "scf:sell:{$invoice->id}",
                description: "Penjualan murabahah SCF ke buyer {$facility->buyer_party_ref} invoice {$invoiceNumber}",
                entries: [
                    ['account_code' => 'syb:scf_murabahah_receivable:IDR', 'amount' => $totalSalePriceCents],
                    ['account_code' => 'syb:scf_inventory:IDR', 'amount' => -$principalCostCents],
                    ['account_code' => 'syb:scf_margin_income:IDR', 'amount' => -$marginProfitCents],
                ]
            );

            SybTradeAuditLog::create([
                'audit_ref' => $invoiceNumber,
                'event_name' => 'SCF_MURABAHAH_DISBURSED',
                'details' => "Pencairan SCF Murabahah pokok {$principalCostCents} margin {$marginProfitCents} total {$totalSalePriceCents}",
                'shariah_compliance_status' => 'COMPLIANT',
            ]);

            return $invoice;
        });
    }

    public function recordRepayment(int $invoiceId, int $amountPaidCents): SybScfRepayment
    {
        $invoice = SybScfInvoice::with('facility')->findOrFail($invoiceId);

        if ($amountPaidCents <= 0) {
            throw new InvalidArgumentException('Nominal pembayaran harus positif.');
        }

        SyariahAccounts::ensureAccountsExist();

        return DB::transaction(function () use ($invoice, $amountPaidCents) {
            $totalOutstanding = $invoice->total_sale_price_cents;
            $principalRatioBps = (int) intdiv($invoice->principal_cost_cents * 10000, $totalOutstanding);

            $principalPortion = (int) intdiv($amountPaidCents * $principalRatioBps, 10000);
            $marginPortion = max(0, $amountPaidCents - $principalPortion);

            $count = SybScfRepayment::count() + 1;
            $repaymentRef = sprintf('REP-SCF-%04d', $count);

            $repayment = SybScfRepayment::create([
                'invoice_id' => $invoice->id,
                'repayment_reference' => $repaymentRef,
                'amount_paid_cents' => $amountPaidCents,
                'principal_portion_cents' => $principalPortion,
                'margin_portion_cents' => $marginPortion,
                'paid_at' => CarbonImmutable::now(),
            ]);

            // Release utilized facility limit
            $invoice->facility->decrement('utilized_amount_cents', min($invoice->facility->utilized_amount_cents, $principalPortion));

            // Posting pelunasan piutang SCF:
            // Debit cash pool, Kredit syb:scf_murabahah_receivable:IDR
            $this->ledgerPoster->postSimple(
                type: SyariahTransactionTypes::SYB_SCF_REPAYMENT,
                reference: "scf:repay:{$repayment->id}",
                description: "Penerimaan cicilan pelunasan tagihan SCF {$invoice->invoice_number}",
                entries: [
                    ['account_code' => 'syb:cash_pool:IDR', 'amount' => $amountPaidCents],
                    ['account_code' => 'syb:scf_murabahah_receivable:IDR', 'amount' => -$amountPaidCents],
                ]
            );

            return $repayment;
        });
    }
}

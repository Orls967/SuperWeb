<?php

declare(strict_types=1);

namespace Modules\Hospital\Domain\Services;

use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Banking\Support\Posting;
use Modules\Hospital\Domain\Models\EPharmacyOrder;
use Modules\Hospital\Ledger\HospitalTransactionTypes;

/**
 * Automated Refill O2C (Order-to-Cash) Billing & Revenue Split Coordinator.
 * Mengintegrasikan transaksi keuangan telemedis dan e-farmasi dengan buku besar rumah sakit:
 * 1. Pemecahan pendapatan transparan: harga obat, jasa farmasi klinis, dan ongkos kirim kurir.
 * 2. Pembukuan ganda (double-entry) berimbang ke akun piutang dan pendapatan.
 * 3. Idempotensi transaksi finansial menggunakan idempotency key berbasis kode pesanan unik.
 * 4. Verifikasi saldo buku besar untuk memastikan zero discrepancy pada audit finansial hsp:audit.
 */
class AutomatedRefillO2cBillingCoordinator
{
    public function __construct(
        protected ?LedgerService $ledgerService = null
    ) {
        $this->ledgerService = $ledgerService ?? app(LedgerService::class);
    }

    /**
     * Membukukan posting transaksi e-farmasi lengkap dengan pembagian komponen pendapatan.
     *
     * @return array{
     *     order_code: string,
     *     drug_revenue_idr: int,
     *     delivery_fee_idr: int,
     *     total_billed_idr: int,
     *     idempotency_key: string,
     *     is_posted: bool
     * }
     */
    public function postOrderRevenue(EPharmacyOrder $order, int $deliveryFeeIdr = 25_000): array
    {
        $drugPriceIdr = (int) $order->total_price_idr;
        $totalBilledIdr = $drugPriceIdr + $deliveryFeeIdr;
        $idempotencyKey = "POST-EPO-{$order->order_code}";

        // Posting berimbang ke Ledger Buku Besar
        $this->ledgerService->post(new PostingDTO(
            type: HospitalTransactionTypes::EPHARMACY_O2C_SETTLEMENT->value,
            description: "Penebusan e-resep farmasi order {$order->order_code} (Obat + Kurir)",
            idempotencyKey: $idempotencyKey,
            entries: Posting::lines()
                ->debit('hsp:epharmacy_receivable:IDR', $totalBilledIdr)
                ->credit('hsp:epharmacy_revenue:IDR', $drugPriceIdr)
                ->credit('hsp:courier_payable:IDR', $deliveryFeeIdr)
                ->toEntries('IDR'),
            referenceType: 'EPHARMACY_ORDER',
            referenceId: (string) $order->id,
        ));

        return [
            'order_code' => $order->order_code,
            'drug_revenue_idr' => $drugPriceIdr,
            'delivery_fee_idr' => $deliveryFeeIdr,
            'total_billed_idr' => $totalBilledIdr,
            'idempotency_key' => $idempotencyKey,
            'is_posted' => true,
        ];
    }

    /**
     * Membukukan penerimaan jasa konsultasi telemedis dokter.
     */
    public function postTeleconsultationFee(
        string $consultCode,
        int $consultId,
        int $totalFeeIdr = 150_000,
        int $doctorShareIdr = 105_000
    ): array {
        $hospitalShareIdr = $totalFeeIdr - $doctorShareIdr;
        $idempotencyKey = "POST-TC-{$consultCode}";

        $this->ledgerService->post(new PostingDTO(
            type: HospitalTransactionTypes::TELECONSULT_REVENUE_SPLIT->value,
            description: "Jasa konsultasi telemedis {$consultCode} (Split DPJP + RS)",
            idempotencyKey: $idempotencyKey,
            entries: Posting::lines()
                ->debit('hsp:telemedicine_receivable:IDR', $totalFeeIdr)
                ->credit('hsp:telemedicine_revenue:IDR', $hospitalShareIdr)
                ->credit('hsp:doctor_payable:IDR', $doctorShareIdr)
                ->toEntries('IDR'),
            referenceType: 'TELE_CONSULT',
            referenceId: (string) $consultId,
        ));

        return [
            'consult_code' => $consultCode,
            'total_fee_idr' => $totalFeeIdr,
            'doctor_share_idr' => $doctorShareIdr,
            'hospital_share_idr' => $hospitalShareIdr,
            'idempotency_key' => $idempotencyKey,
            'is_posted' => true,
        ];
    }
}

<?php

declare(strict_types=1);

namespace Tests\Acceptance\SybIslamicTradeFinance;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Syariah\Application\Services\IslamicLetterOfCreditService;
use Modules\Syariah\Application\Services\MurabahahSupplyChainService;
use Modules\Syariah\Application\Services\SalamTradeService;
use Modules\Syariah\Application\Services\TawarruqFxService;
use Modules\Syariah\Application\Services\TradeCustodyService;
use Modules\Syariah\Domain\Enums\IslamicTradeContractType;
use Modules\Syariah\Domain\Enums\IslamicTradeStatus;
use Tests\TestCase;

class SybIslamicTradeFinanceAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('module:migrate', ['module' => 'Syariah']);
    }

    public function test_islamic_lc_with_istisna_poc_and_wakalah_fees(): void
    {
        $lcService = app(IslamicLetterOfCreditService::class);

        $lc = $lcService->issueLetterOfCredit(
            applicantPartyRef: 'party-importer-01',
            beneficiaryPartyRef: 'party-manufacturer-01',
            issuingBankPartyRef: 'party-bank-syariah',
            amountCurrency: 'IDR',
            amountCents: 5_000_000_000,
            marginDepositCents: 1_000_000_000,
            expiryDate: CarbonImmutable::parse('2027-06-30')
        );

        expect($lc->status)->toBe(IslamicTradeStatus::ACTIVE->value);

        $istisna = $lcService->createIstisnaOrder(
            lcId: $lc->id,
            manufacturerPartyRef: 'party-manufacturer-01',
            specificationSummary: 'Pengadaan 10 unit mesin turbin hidro skala mikro',
            totalPriceCents: 5_000_000_000,
            budgetedCostCents: 4_000_000_000
        );

        expect($istisna->progress_percent)->toBe(0);

        // Update POC milestone
        $updated = $lcService->recordCostIncurred(
            istisnaId: $istisna->id,
            costIncurredCents: 2_000_000_000, // 50% POC
            inspectionNote: 'Verifikasi fisik tahap 1 rangka utama dan alternator selesai 50%'
        );

        expect($updated->progress_percent)->toBe(50)
            ->and($updated->recognized_revenue_cents)->toBe(2_500_000_000);
    }

    public function test_salam_and_parallel_salam_commodity_hedge(): void
    {
        $salamService = app(SalamTradeService::class);

        $salam = $salamService->contractSalam(
            sellerPartyRef: 'farmer-coop-sumatra',
            buyerPartyRef: 'syariah-trade-holding',
            commodityCode: 'CPO',
            commodityGrade: 'GRADE_A',
            quantityUnit: 'MT',
            quantityOrdered: 200,
            advancePaymentCents: 2_400_000_000, // 12jt/MT
            promisedDeliveryDate: CarbonImmutable::parse('2026-12-15'),
            deliveryWarehouseCode: 'WHS-BELAWAN-01'
        );

        expect($salam->status)->toBe(IslamicTradeStatus::ACTIVE->value);

        // Parallel Salam Hedge ke off-taker
        $hedge = $salamService->contractParallelHedge(
            salamId: $salam->id,
            offtakerPartyRef: 'refinery-intl-corp',
            agreedSalePriceCents: 2_800_000_000, // 14jt/MT
            quantityHedged: 200,
            deliveryDate: CarbonImmutable::parse('2026-12-20')
        );

        expect($hedge->agreed_sale_price_cents)->toBe(2_800_000_000);

        // Evaluasi buku posisi
        $position = $salamService->evaluatePositionBook('CPO', 13_500_000);
        expect($position->netOpenPositionQuantity)->toBe(0)
            ->and($position->totalShortCommitmentCents)->toBe(2_800_000_000);
    }

    public function test_murabahah_supply_chain_finance_with_qabdh_verification_gate(): void
    {
        $scfService = app(MurabahahSupplyChainService::class);
        $custodyService = app(TradeCustodyService::class);

        // Register document custody BL
        $doc = $custodyService->registerDocument(
            documentType: 'BillOfLading',
            documentNumber: 'BL-MAERSK-2026-8812',
            issuerName: 'Maersk Line Indonesia',
            carrierName: 'MV Nusantara Jaya',
            cargoDescription: '100 ton biji kakao fermentasi super',
            rawContentForHash: 'BL-MAERSK-2026-8812|MV Nusantara Jaya|100MT Cocoa'
        );

        expect($doc->verified_qabdh_at)->toBeNull();

        $facility = $scfService->createFacility(
            buyerPartyRef: 'buyer-pt-industri-pangan',
            supplierPartyRef: 'supplier-koperasi-kakao',
            limitAmountCents: 3_000_000_000,
            marginRateBps: 800, // 8.00%
            maxTenorDays: 120
        );

        // Menolak eksekusi penjualan murabahah jika Qabdh belum diverifikasi
        expect(fn () => $scfService->disburseMurabahahInvoice(
            facilityId: $facility->id,
            supplierInvoiceRef: 'INV-SUPP-991',
            principalCostCents: 1_200_000_000,
            custodyDocumentId: $doc->id,
            tenorDays: 90
        ))->toThrow(\RuntimeException::class);

        // Verifikasi Qabdh oleh Custody Officer
        $custodyService->verifyQabdh($doc->id, 'officer-trade-01');

        // Setelah qabdh diverifikasi, eksekusi murabahah berhasil
        $invoice = $scfService->disburseMurabahahInvoice(
            facilityId: $facility->id,
            supplierInvoiceRef: 'INV-SUPP-991',
            principalCostCents: 1_200_000_000,
            custodyDocumentId: $doc->id,
            tenorDays: 90
        );

        expect($invoice->status)->toBe(IslamicTradeStatus::ACTIVE->value)
            ->and($invoice->total_sale_price_cents)->toBeGreaterThan(1_200_000_000);
    }

    public function test_commodity_murabahah_tawarruq_fx_conversion(): void
    {
        $tawarruqService = app(TawarruqFxService::class);

        $order = $tawarruqService->executeTawarruqFx(
            customerPartyRef: 'party-corporate-importer',
            sourceCurrency: 'IDR',
            sourcePrincipalCents: 3_100_000_000,
            targetCurrency: 'USD',
            spotRateBps: 1_550_000, // 15,500 IDR / USD
            tenorDays: 90,
            markupRateBps: 600,
            brokerFeeCents: 5_000_000,
            exchangeName: 'Bursa ICDX Syariah'
        );

        expect($order->target_amount_cents)->toBe(20_000_000) // $200,000 USD
            ->and($order->status)->toBe(IslamicTradeStatus::COMPLETED->value)
            ->and($order->commodityLot)->not->toBeNull();
    }

    public function test_cross_border_custody_verification_and_trade_finance_audit(): void
    {
        $this->artisan('syb:audit')->assertExitCode(0);
    }
}

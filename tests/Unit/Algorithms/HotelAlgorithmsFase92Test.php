<?php

declare(strict_types=1);

namespace Tests\Unit\Algorithms;

use Modules\Hotel\Domain\Algorithms\ChurnRiskCertificateVerifier;
use Modules\Hotel\Domain\Algorithms\ChurnRiskScorer;
use Modules\Hotel\Domain\Algorithms\FolioSplitCertificateVerifier;
use Modules\Hotel\Domain\Algorithms\FolioSplitRouter;
use Modules\Hotel\Domain\Algorithms\FractionalScheduleCertificateVerifier;
use Modules\Hotel\Domain\Algorithms\FractionalUsageScheduler;
use Modules\Hotel\Domain\Algorithms\LoyaltyNightTierEngine;
use Modules\Hotel\Domain\Algorithms\LoyaltyTierCertificateVerifier;
use Tests\TestCase;

final class HotelAlgorithmsFase92Test extends TestCase
{
    public function test_h6_folio_split_router_prompt_vector(): void
    {
        $router = new FolioSplitRouter;
        $verifier = new FolioSplitCertificateVerifier;

        $lineItems = [
            ['line_id' => 'l1', 'category' => 'ROOM_CHARGE', 'amount_idr' => 1000000],
            ['line_id' => 'l2', 'category' => 'HOTEL_TAX', 'amount_idr' => 100000],
            ['line_id' => 'l3', 'category' => 'SERVICE_CHARGE', 'amount_idr' => 100000],
            ['line_id' => 'l4', 'category' => 'MINIBAR', 'amount_idr' => 250000],
            ['line_id' => 'l5', 'category' => 'SPA', 'amount_idr' => 500000],
        ];

        // Skenario 1: Corporate Billing Enabled dengan Limit Cukup (IDR 2.000.000)
        $cert = $router->route('folio-9201', true, 2000000, $lineItems);
        $this->assertTrue($verifier->verify($cert));
        $this->assertSame(1950000, $cert->totalAmountIdr);
        $this->assertSame(1200000, $cert->corporateAmountIdr); // Kamar + Tax + Service
        $this->assertSame(750000, $cert->personalAmountIdr); // Minibar + Spa

        // Skenario 2: Corporate Billing Disabled -> Semua Masuk Personal
        $certPersonalOnly = $router->route('folio-9202', false, 5000000, $lineItems);
        $this->assertTrue($verifier->verify($certPersonalOnly));
        $this->assertSame(0, $certPersonalOnly->corporateAmountIdr);
        $this->assertSame(1950000, $certPersonalOnly->personalAmountIdr);
    }

    public function test_h7_loyalty_night_tier_engine_prompt_vector(): void
    {
        $engine = new LoyaltyNightTierEngine;
        $verifier = new LoyaltyTierCertificateVerifier;

        // 1. Kualifikasi Basic (Total 8 malam)
        $certBasic = $engine->evaluate('gst-1', 5, 3, 1000000, 500);
        $this->assertTrue($verifier->verify($certBasic));
        $this->assertSame('BASIC', $certBasic->tierName);
        $this->assertSame(1.00, $certBasic->pointsMultiplier);
        $this->assertSame(1000, $certBasic->pointsEarnedThisStay);

        // 2. Kualifikasi Silver (Total 12 malam)
        $certSilver = $engine->evaluate('gst-2', 10, 2, 2000000, 2000);
        $this->assertTrue($verifier->verify($certSilver));
        $this->assertSame('SILVER', $certSilver->tierName);
        $this->assertSame(1.15, $certSilver->pointsMultiplier);
        $this->assertSame(2300, $certSilver->pointsEarnedThisStay);

        // 3. Kualifikasi Gold (Total 30 malam)
        $certGold = $engine->evaluate('gst-3', 25, 5, 5000000, 5000);
        $this->assertTrue($verifier->verify($certGold));
        $this->assertSame('GOLD', $certGold->tierName);
        $this->assertSame(1.30, $certGold->pointsMultiplier);
        $this->assertSame(6500, $certGold->pointsEarnedThisStay);

        // 4. Kualifikasi Platinum (Total 55 malam)
        $certPlat = $engine->evaluate('gst-4', 50, 5, 10000000, 10000);
        $this->assertTrue($verifier->verify($certPlat));
        $this->assertSame('PLATINUM', $certPlat->tierName);
        $this->assertSame(1.50, $certPlat->pointsMultiplier);
        $this->assertSame(15000, $certPlat->pointsEarnedThisStay);
    }

    public function test_h8_fractional_usage_scheduler_prompt_vector(): void
    {
        $scheduler = new FractionalUsageScheduler;
        $verifier = new FractionalScheduleCertificateVerifier;

        // Pemilik memiliki 4 fraksi (4 minggu hak inap per tahun).
        // Meminta minggu 26 (Peak Summer) dan minggu 51 (Peak Year End).
        $cert = $scheduler->schedule('unit-villa-01', 'owner-44', 4, [26, 51], 2026);

        $this->assertTrue($verifier->verify($cert));
        $this->assertSame(4, $cert->totalFractionsOwned);
        $this->assertSame(2, $cert->allocatedWeeksCount);
        $this->assertSame(2, $cert->rentalPoolWeeksCount);
        $this->assertCount(4, $cert->scheduledWeeks);

        // Pastikan tidak ada minggu ganda
        $weeks = array_column($cert->scheduledWeeks, 'week_number');
        $this->assertSame(count($weeks), count(array_unique($weeks)));
    }

    public function test_h9_churn_risk_scorer_prompt_vector(): void
    {
        $scorer = new ChurnRiskScorer;
        $verifier = new ChurnRiskCertificateVerifier;

        // 1. Tamu Sangat Loyal (Low Risk)
        $certLow = $scorer->score('gst-loyal', 15, 8, 2500000, 0, 4.9);
        $this->assertTrue($verifier->verify($certLow));
        $this->assertSame('LOW_RISK', $certLow->riskCategory);
        $this->assertLessThan(0.30, $certLow->churnScore);

        // 2. Tamu Berisiko Tinggi (High Risk: lama tak menginap, ada keluhan tertunda, rating rendah)
        $certHigh = $scorer->score('gst-churn', 220, 1, 1500000, 2, 2.0);
        $this->assertTrue($verifier->verify($certHigh));
        $this->assertSame('HIGH_RISK', $certHigh->riskCategory);
        $this->assertGreaterThanOrEqual(0.65, $certHigh->churnScore);
        $this->assertSame('DIRECT_GM_CONCIERGE_OUTREACH', $certHigh->actionPlan['action']);
    }
}

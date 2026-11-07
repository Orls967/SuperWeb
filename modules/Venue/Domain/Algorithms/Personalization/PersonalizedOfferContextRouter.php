<?php

declare(strict_types=1);

namespace Modules\Venue\Domain\Algorithms\Personalization;

/**
 * Algoritma V16: PersonalizedOfferContextRouter.
 * Mengarahkan penawaran promosi kontekstual on-site bagi member dan tamu VIP di lokasi,
 * menerapkan pelindung kelelahan notifikasi (anti-fatigue filter), dan memastikan batas bawah
 * harga pokok penjualan (margin floor guardrail) tidak pernah terlanggar.
 */
final class PersonalizedOfferContextRouter
{
    private const int COOLDOWN_SECONDS = 3600; // 1 jam jeda antar promo

    private const int MAX_OFFERS_PER_NIGHT = 3;

    /**
     * Evaluasi kelayakan tamu menerima penawaran diskon on-site.
     */
    public function evaluateOffer(
        int $userId,
        int $currentZoneId,
        string $membershipTierCode,
        int $baseItemPriceIdr,
        int $itemCogsFloorPriceIdr,
        int $lastOfferTimestamp,
        int $offersSentTonightCount,
        int $currentTimestamp,
        string $saltSecret = 'OFFER_ROUTER_SALT_2026'
    ): PersonalizedOfferResult {
        // 1. Cek Anti-Fatigue Filters
        if (($currentTimestamp - $lastOfferTimestamp) < self::COOLDOWN_SECONDS) {
            return new PersonalizedOfferResult(
                isEligible: false,
                discountBps: 0,
                discountedPriceIdr: $baseItemPriceIdr,
                savingsAmountIdr: 0,
                floorGuardrailTriggered: false,
                offerDispatchCode: '',
                rejectionReason: 'COOLDOWN_ACTIVE'
            );
        }

        if ($offersSentTonightCount >= self::MAX_OFFERS_PER_NIGHT) {
            return new PersonalizedOfferResult(
                isEligible: false,
                discountBps: 0,
                discountedPriceIdr: $baseItemPriceIdr,
                savingsAmountIdr: 0,
                floorGuardrailTriggered: false,
                offerDispatchCode: '',
                rejectionReason: 'DAILY_QUOTA_EXHAUSTED'
            );
        }

        // 2. Alokasi Diskon Berdasarkan Tier
        $discountBps = match (strtoupper($membershipTierCode)) {
            'VIP_PLATINUM' => 2500, // 25%
            'GOLD_ELITE' => 1500,   // 15%
            'SILVER_MEMBER' => 1000, // 10%
            default => 500,         // 5%
        };

        // 3. Kalkulasi Harga dengan Margin Floor Guardrail
        $rawDiscountAmount = (int) intdiv($baseItemPriceIdr * $discountBps, 10000);
        $candidatePrice = $baseItemPriceIdr - $rawDiscountAmount;

        $floorTriggered = false;
        if ($candidatePrice < $itemCogsFloorPriceIdr) {
            $finalPrice = $itemCogsFloorPriceIdr;
            $floorTriggered = true;
        } else {
            $finalPrice = $candidatePrice;
        }

        $savingsAmount = max(0, $baseItemPriceIdr - $finalPrice);
        $slotHour = (int) intdiv($currentTimestamp, 3600);
        $dispatchCode = sprintf('PROMO-CTX-%d-%d-%d', $userId, $currentZoneId, $slotHour);

        $payload = sprintf(
            '1:%d:%d:%d:%d:%s:NONE',
            $discountBps,
            $finalPrice,
            $savingsAmount,
            $floorTriggered ? 1 : 0,
            $dispatchCode
        );
        $signature = hash_hmac('sha256', $payload, $saltSecret);

        return new PersonalizedOfferResult(
            isEligible: true,
            discountBps: $discountBps,
            discountedPriceIdr: $finalPrice,
            savingsAmountIdr: $savingsAmount,
            floorGuardrailTriggered: $floorTriggered,
            offerDispatchCode: $dispatchCode,
            rejectionReason: null,
            certificateSignature: $signature
        );
    }
}

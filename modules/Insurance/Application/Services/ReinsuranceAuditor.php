<?php

declare(strict_types=1);

namespace Modules\Insurance\Application\Services;

use Modules\Banking\Contracts\Ledger;
use Modules\Insurance\Domain\Models\CatBondCoupon;
use Modules\Insurance\Domain\Models\CessionEvent;
use Modules\Insurance\Domain\Models\ReinsuranceBordereau;
use Modules\Insurance\Domain\Models\ReinsuranceTreaty;

final class ReinsuranceAuditor
{
    public function __construct(
        private readonly Ledger $ledger,
    ) {}

    /**
     * @return list<string> pesan galat, kosong bila bersih
     */
    public function audit(): array
    {
        $errors = [];

        // 1. Invarian gross = retained + ceded pada semua cession events
        $events = CessionEvent::all();
        foreach ($events as $event) {
            $sum = $event->retained_idr + $event->ceded_idr;
            if ($sum !== $event->gross_idr) {
                $errors[] = "Cession event {$event->event_key}: gross ({$event->gross_idr}) != retained ({$event->retained_idr}) + ceded ({$event->ceded_idr})";
            }
        }

        // 2. Bordereaux = lines = cession lines
        $bordereaux = ReinsuranceBordereau::with('lines')->get();
        foreach ($bordereaux as $bdx) {
            $sumLines = (int) $bdx->lines->sum('amount_idr');
            if ($sumLines !== (int) $bdx->total_ceded_idr) {
                $errors[] = "Bordereau {$bdx->number}: total_ceded ({$bdx->total_ceded_idr}) != sum lines ({$sumLines})";
            }
        }

        // 3. Cat bond coupon consistency
        $coupons = CatBondCoupon::with('payouts')->get();
        foreach ($coupons as $coupon) {
            $sumPayouts = (int) $coupon->payouts->sum('amount_idr');
            if ($sumPayouts !== (int) $coupon->total_idr) {
                $errors[] = "Cat bond coupon #{$coupon->id} ({$coupon->quarter_key}): total ({$coupon->total_idr}) != sum payouts ({$sumPayouts})";
            }
        }

        return $errors;
    }
}


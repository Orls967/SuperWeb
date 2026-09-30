<?php

declare(strict_types=1);

namespace Modules\AutoServe\Application\Services;

use Modules\AutoServe\Domain\Enums\BookingStatus;
use Modules\AutoServe\Domain\Models\Booking;
use Modules\Mall\Contracts\TenantSalesProvider;
use Modules\Mall\Domain\Models\Lease;

class AutoServeTenantSalesProvider implements TenantSalesProvider
{
    public const WORKSHOP_EXTERNAL_REF = 'AUTOSERVE-DM';

    public function supports(Lease $lease): bool
    {
        return $lease->tenant?->external_ref === self::WORKSHOP_EXTERNAL_REF;
    }

    public function getMonthlySales(Lease $lease, string $periodMonth): int
    {
        if (! $this->supports($lease)) {
            return 0;
        }

        [$year, $month] = explode('-', $periodMonth);

        $totalSales = (float) Booking::query()
            ->where(function ($query) {
                $query->where('payment_status', 'paid')
                    ->orWhereIn('status', [BookingStatus::Completed, BookingStatus::Invoiced]);
            })
            ->where(function ($query) use ($year, $month) {
                $query->where(function ($q) use ($year, $month) {
                    $q->whereNotNull('paid_at')
                        ->whereYear('paid_at', (int) $year)
                        ->whereMonth('paid_at', (int) $month);
                })->orWhere(function ($q) use ($year, $month) {
                    $q->whereNull('paid_at')
                        ->whereYear('booking_date', (int) $year)
                        ->whereMonth('booking_date', (int) $month);
                });
            })
            ->sum('grand_total');

        return (int) round($totalSales);
    }

    public function getTransactionCount(Lease $lease, string $periodMonth): int
    {
        if (! $this->supports($lease)) {
            return 0;
        }

        [$year, $month] = explode('-', $periodMonth);

        return Booking::query()
            ->where(function ($query) {
                $query->where('payment_status', 'paid')
                    ->orWhereIn('status', [BookingStatus::Completed, BookingStatus::Invoiced]);
            })
            ->where(function ($query) use ($year, $month) {
                $query->where(function ($q) use ($year, $month) {
                    $q->whereNotNull('paid_at')
                        ->whereYear('paid_at', (int) $year)
                        ->whereMonth('paid_at', (int) $month);
                })->orWhere(function ($q) use ($year, $month) {
                    $q->whereNull('paid_at')
                        ->whereYear('booking_date', (int) $year)
                        ->whereMonth('booking_date', (int) $month);
                });
            })
            ->count();
    }
}

<?php

declare(strict_types=1);

namespace Modules\Mall\Application\Queries;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Modules\Mall\Domain\Models\EventBooking;
use Modules\Mall\Domain\Models\EventSpace;

class EventCalendarQuery
{
    /**
     * @return array{
     *     spaces: Collection,
     *     bookings: Collection,
     *     month_year: string,
     *     total_revenue: int
     * }
     */
    public function get(?string $month = null): array
    {
        $targetMonth = $month ? Carbon::parse($month.'-01') : Carbon::now()->startOfMonth();
        $startOfMonth = $targetMonth->copy()->startOfMonth();
        $endOfMonth = $targetMonth->copy()->endOfMonth();

        $spaces = EventSpace::where('is_active', true)->get();

        $bookings = EventBooking::with(['space', 'customer', 'tenant'])
            ->where(function ($q) use ($startOfMonth, $endOfMonth) {
                $q->whereBetween('start_date', [$startOfMonth->toDateString(), $endOfMonth->toDateString()])
                    ->orWhereBetween('end_date', [$startOfMonth->toDateString(), $endOfMonth->toDateString()]);
            })
            ->orderBy('start_date', 'asc')
            ->get();

        $totalRevenue = (int) EventBooking::whereIn('status', ['confirmed', 'ongoing', 'completed'])->sum('paid_amount');

        return [
            'spaces' => $spaces,
            'bookings' => $bookings,
            'month_year' => $targetMonth->format('F Y'),
            'total_revenue' => $totalRevenue,
        ];
    }
}

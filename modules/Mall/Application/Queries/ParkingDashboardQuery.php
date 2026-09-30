<?php

declare(strict_types=1);

namespace Modules\Mall\Application\Queries;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Modules\Mall\Domain\Enums\MemberStatus;
use Modules\Mall\Domain\Enums\ParkingPaymentStatus;
use Modules\Mall\Domain\Enums\ParkingSessionStatus;
use Modules\Mall\Domain\Models\ParkingMember;
use Modules\Mall\Domain\Models\ParkingSession;
use Modules\Mall\Domain\Models\ParkingZone;

class ParkingDashboardQuery
{
    /**
     * Ringkasan operasional parkir hari ini.
     *
     * @return array{
     *     zones: Collection<int, ParkingZone>,
     *     total_capacity: int,
     *     total_occupied: int,
     *     occupancy_rate: float,
     *     active_sessions: int,
     *     exits_today: int,
     *     revenue_today: int,
     *     validated_today: int,
     *     validation_burden_today: int,
     *     lost_tickets_today: int,
     *     active_members: int,
     *     expiring_members: int
     * }
     */
    public function execute(int $propertyId, ?Carbon $date = null): array
    {
        $date = $date ?? Carbon::today();
        $startOfDay = $date->copy()->startOfDay();
        $endOfDay = $date->copy()->endOfDay();

        $zones = ParkingZone::where('property_id', $propertyId)
            ->where('is_active', true)
            ->orderBy('code')
            ->get();

        $totalCapacity = (int) $zones->sum('total_capacity');
        $totalOccupied = (int) $zones->sum('current_occupancy');

        $exitedToday = ParkingSession::where('property_id', $propertyId)
            ->whereBetween('exit_time', [$startOfDay, $endOfDay]);

        $revenueToday = (int) ParkingSession::where('property_id', $propertyId)
            ->whereBetween('paid_at', [$startOfDay, $endOfDay])
            ->where('payment_status', ParkingPaymentStatus::PAID)
            ->sum('total_fee');

        $validatedToday = ParkingSession::where('property_id', $propertyId)
            ->whereNotNull('validated_by_tenant_id')
            ->whereBetween('entry_time', [$startOfDay, $endOfDay]);

        return [
            'zones' => $zones,
            'total_capacity' => $totalCapacity,
            'total_occupied' => $totalOccupied,
            'occupancy_rate' => $totalCapacity > 0
                ? round(($totalOccupied / $totalCapacity) * 100, 1)
                : 0.0,
            'active_sessions' => ParkingSession::where('property_id', $propertyId)
                ->where('status', ParkingSessionStatus::ACTIVE)
                ->count(),
            'exits_today' => (clone $exitedToday)->count(),
            'revenue_today' => $revenueToday,
            'validated_today' => (clone $validatedToday)->count(),
            'validation_burden_today' => (int) (clone $validatedToday)->sum('discount_amount'),
            'lost_tickets_today' => (clone $exitedToday)->where('is_lost_ticket', true)->count(),
            'active_members' => ParkingMember::where('property_id', $propertyId)
                ->where('status', MemberStatus::ACTIVE)
                ->count(),
            'expiring_members' => ParkingMember::where('property_id', $propertyId)
                ->where('status', MemberStatus::ACTIVE)
                ->whereDate('end_date', '<=', $date->copy()->addDays(7)->toDateString())
                ->count(),
        ];
    }
}

<?php

declare(strict_types=1);

namespace Modules\Logistics\Application\TickHandlers;

use Carbon\CarbonInterface;
use Modules\Core\Contracts\SimTickHandlerInterface;
use Modules\Logistics\Domain\Models\ContainerDwell;

/**
 * Handler simulasi harian untuk modul Logistics: mengakrualkan denda demurrage & detention armada
 * dengan tarif bertingkat (tiered penalty) deterministik setelah batas masa tenggang (free time).
 */
class LogisticsDemurrageTickHandler implements SimTickHandlerInterface
{
    public const FREE_TIME_DAYS = 5;

    public const TIER_1_DAILY_PENALTY_IDR = 250000; // Hari 6 - 10

    public const TIER_2_DAILY_PENALTY_IDR = 500000; // Hari 11+

    public function name(): string
    {
        return 'logistics_demurrage';
    }

    /**
     * @return list<string>
     */
    public function dependencies(): array
    {
        return ['hotel_night_audit']; // Berjalan setelah hotel night audit
    }

    public function shouldRun(CarbonInterface $virtualNow, int $dayNumber): bool
    {
        return true;
    }

    public function handle(CarbonInterface $virtualNow, int $dayNumber, ?callable $commandRunner = null): array
    {
        $activeDwells = ContainerDwell::where('status', 'active')->get();
        $totalDwells = $activeDwells->count();

        $containersPenalized = 0;
        $totalDemurrageAccruedIdr = 0;

        foreach ($activeDwells as $dwell) {
            $arrivedAt = $dwell->arrived_at ?? $virtualNow->copy()->subDays(self::FREE_TIME_DAYS + 1);
            $dwellDays = (int) $virtualNow->diffInDays($arrivedAt);

            if ($dwellDays > self::FREE_TIME_DAYS) {
                $overdueDays = $dwellDays - self::FREE_TIME_DAYS;
                $dailyRate = $overdueDays <= 5 ? self::TIER_1_DAILY_PENALTY_IDR : self::TIER_2_DAILY_PENALTY_IDR;

                $totalDemurrageAccruedIdr += $dailyRate;
                $containersPenalized++;

                if (isset($dwell->accumulated_demurrage)) {
                    $dwell->accumulated_demurrage += $dailyRate;
                    $dwell->save();
                }
            }
        }

        return [
            'day' => $dayNumber,
            'virtual_date' => $virtualNow->toDateString(),
            'total_active_dwells' => $totalDwells,
            'containers_in_penalty' => $containersPenalized,
            'total_demurrage_accrued_idr' => $totalDemurrageAccruedIdr,
            'free_time_threshold_days' => self::FREE_TIME_DAYS,
            'status' => 'ok',
        ];
    }
}

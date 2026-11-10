<?php

declare(strict_types=1);

namespace Modules\Core\Application\Services\Simulation;

use InvalidArgumentException;
use Modules\Core\Contracts\SimClockInterface;
use Modules\Core\Domain\ValueObjects\Simulation\VirtualTimestamp;

/**
 * Layanan orkestrasi dan sinkronisasi Waktu Virtual multi-modul.
 * Mengontrol eksekusi tick harian, interval frekuensi (harian, mingguan, bulanan, tahunan),
 * dan kalkulasi pergeseran waktu virtual deterministik.
 */
final class VirtualClockSynchronizerService
{
    /** @var array<string, array{frequency: string, interval_days: int, last_run_day: int}> */
    private array $registeredSchedules = [];

    public function __construct(
        private readonly SimClockInterface $clock,
    ) {
        $this->registerDefaultSchedules();
    }

    private function registerDefaultSchedules(): void
    {
        $this->registeredSchedules['daily_accounting'] = [
            'frequency' => 'daily',
            'interval_days' => 1,
            'last_run_day' => 0,
        ];
        $this->registeredSchedules['weekly_reconciliation'] = [
            'frequency' => 'weekly',
            'interval_days' => 7,
            'last_run_day' => 0,
        ];
        $this->registeredSchedules['monthly_depreciation'] = [
            'frequency' => 'monthly',
            'interval_days' => 30,
            'last_run_day' => 0,
        ];
        $this->registeredSchedules['annual_closing'] = [
            'frequency' => 'annual',
            'interval_days' => 365,
            'last_run_day' => 0,
        ];
    }

    public function registerSchedule(string $scheduleKey, string $frequency, int $intervalDays): void
    {
        if ($intervalDays <= 0) {
            throw new InvalidArgumentException("Interval hari harus positif: {$intervalDays}");
        }

        $this->registeredSchedules[$scheduleKey] = [
            'frequency' => $frequency,
            'interval_days' => $intervalDays,
            'last_run_day' => 0,
        ];
    }

    /**
     * Memeriksa jadwal mana yang jatuh tempo untuk dieksekusi pada hari virtual saat ini.
     *
     * @return array<int, string>
     */
    public function getDueSchedules(int $currentVirtualDay): array
    {
        $due = [];
        foreach ($this->registeredSchedules as $key => $config) {
            $lastRun = $config['last_run_day'];
            $interval = $config['interval_days'];

            if ($currentVirtualDay >= $lastRun + $interval || ($currentVirtualDay === 1 && $lastRun === 0)) {
                $due[] = $key;
            }
        }

        return $due;
    }

    public function markScheduleExecuted(string $scheduleKey, int $currentVirtualDay): void
    {
        if (isset($this->registeredSchedules[$scheduleKey])) {
            $this->registeredSchedules[$scheduleKey]['last_run_day'] = $currentVirtualDay;
        }
    }

    /**
     * Menghitung timestamp virtual saat ini berdasarkan clock terdaftar.
     */
    public function getCurrentVirtualTimestamp(): VirtualTimestamp
    {
        $day = $this->clock->currentDay();

        return VirtualTimestamp::fromDayAndSecond(max(1, $day), 0);
    }

    /**
     * Menghitung proyeksi tanggal kalender berdasarkan offset hari virtual dari genesis epoch.
     */
    public function getVirtualCalendarDate(string $genesisDate = '2026-01-01'): string
    {
        $day = max(1, $this->clock->currentDay());
        $timestamp = strtotime($genesisDate) + (($day - 1) * 86400);

        return date('Y-m-d', $timestamp);
    }

    /**
     * @return array<string, array{frequency: string, interval_days: int, last_run_day: int}>
     */
    public function getAllSchedules(): array
    {
        return $this->registeredSchedules;
    }
}

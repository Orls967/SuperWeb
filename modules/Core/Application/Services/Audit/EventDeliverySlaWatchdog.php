<?php

declare(strict_types=1);

namespace Modules\Core\Application\Services\Audit;

use Modules\Core\Domain\Models\CoreEventDelivery;

/**
 * Pengawas Batas Waktu Layanan Pengiriman (EventDeliverySlaWatchdog)
 *
 * Memantau kepatuhan Service Level Agreement (SLA) pengiriman pesan secara proaktif:
 * - Menghitung distribusi latensi pengiriman
 * - Mengidentifikasi pesan dengan status tertunda yang mendekati pelanggaran SLA (near-breach)
 * - Merekapitulasi rasio kepatuhan SLA (SLA Compliance Ratio) per domain bisnis
 */
class EventDeliverySlaWatchdog
{
    /**
     * @var array<string, int> SLA batas waktu pengiriman dalam detik per prefix topik
     */
    protected array $slaThresholds = [
        'finance.' => 5,
        'hospital.' => 2,
        'logistics.' => 10,
        'procurement.' => 30,
        'inventory.' => 15,
        'telematics.' => 3,
        'default' => 60,
    ];

    /**
     * Memeriksa daftar pengiriman yang sedang berjalan terhadap batas toleransi SLA.
     *
     * @return array{
     *     timestamp: string,
     *     total_in_flight: int,
     *     breached_count: int,
     *     near_breach_count: int,
     *     compliance_percentage: float,
     *     at_risk_deliveries: array<int, array{delivery_id: int, topic: string, age_seconds: int, sla_seconds: int}>
     * }
     */
    public function monitorInFlight(): array
    {
        $inFlight = CoreEventDelivery::query()
            ->whereIn('status', ['pending', 'retrying', 'dispatched'])
            ->with('event')
            ->get();

        $total = $inFlight->count();
        $breached = 0;
        $nearBreach = 0;
        $atRisk = [];
        $now = time();

        foreach ($inFlight as $delivery) {
            $topic = (string) ($delivery->event?->topic ?? 'default');
            $slaSeconds = $this->resolveSlaSeconds($topic);
            $createdAt = $delivery->created_at ? $delivery->created_at->timestamp : $now;
            $age = max(0, $now - $createdAt);

            if ($age > $slaSeconds) {
                $breached++;
                if (count($atRisk) < 10) {
                    $atRisk[] = [
                        'delivery_id' => $delivery->id,
                        'topic' => $topic,
                        'age_seconds' => $age,
                        'sla_seconds' => $slaSeconds,
                    ];
                }
            } elseif ($age > ($slaSeconds * 0.7)) {
                $nearBreach++;
            }
        }

        $compliance = $total > 0
            ? round((($total - $breached) / $total) * 100, 2)
            : 100.0;

        return [
            'timestamp' => date('Y-m-d H:i:s'),
            'total_in_flight' => $total,
            'breached_count' => $breached,
            'near_breach_count' => $nearBreach,
            'compliance_percentage' => $compliance,
            'at_risk_deliveries' => $atRisk,
        ];
    }

    /**
     * Menentukan batas waktu SLA pengiriman dalam detik berdasarkan topik event.
     */
    public function resolveSlaSeconds(string $topic): int
    {
        foreach ($this->slaThresholds as $prefix => $seconds) {
            if ($prefix !== 'default' && str_starts_with($topic, $prefix)) {
                return $seconds;
            }
        }

        return $this->slaThresholds['default'];
    }

    /**
     * Memperbarui batas waktu toleransi SLA untuk prefix topik tertentu secara dinamis.
     */
    public function setCustomSlaThreshold(string $topicPrefix, int $thresholdSeconds): self
    {
        $this->slaThresholds[$topicPrefix] = max(1, $thresholdSeconds);

        return $this;
    }
}

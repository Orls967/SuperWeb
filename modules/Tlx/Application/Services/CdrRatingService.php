<?php

declare(strict_types=1);

namespace Modules\Tlx\Application\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Modules\Shared\Domain\Numerics\UsageRater;
use Modules\Tlx\Domain\Models\IotDevice;
use Modules\Tlx\Domain\Models\TelecomCdr;

class CdrRatingService
{
    /**
     * Ingest raw CDR, calculate rated bytes via UsageRater (1 KB ceiling), and persist.
     */
    public function ingestCdr(array $payload): TelecomCdr
    {
        if (empty($payload['device_id'])) {
            throw new InvalidArgumentException('Device ID is required.');
        }

        if (empty($payload['session_id'])) {
            throw new InvalidArgumentException('Session ID is required.');
        }

        $bytes = (int) ($payload['bytes_transferred'] ?? 0);
        if ($bytes < 0) {
            throw new InvalidArgumentException("Bytes transferred cannot be negative: {$bytes}");
        }

        $duration = (int) ($payload['duration_seconds'] ?? 0);
        if ($duration < 0) {
            throw new InvalidArgumentException("Duration seconds cannot be negative: {$duration}");
        }

        $device = IotDevice::find($payload['device_id']);
        if (! $device) {
            throw new InvalidArgumentException("Device [{$payload['device_id']}] not found.");
        }

        $existing = TelecomCdr::where('device_id', $device->id)
            ->where('session_id', $payload['session_id'])
            ->first();

        if ($existing) {
            throw new InvalidArgumentException("Duplicate CDR session [{$payload['session_id']}] for device [{$device->id}].");
        }

        // Use UsageRater to determine rated bytes (1 KB / 1024 ceil)
        $rating = UsageRater::rate([
            ['session_id' => $payload['session_id'], 'bytes' => $bytes],
        ], 0, 0, 0);

        $ratedBytes = $rating['total_rated_bytes'];

        return TelecomCdr::create([
            'id' => (string) Str::uuid(),
            'device_id' => $device->id,
            'session_id' => $payload['session_id'],
            'started_at' => $payload['started_at'],
            'ended_at' => $payload['ended_at'],
            'bytes_transferred' => $bytes,
            'rated_bytes' => $ratedBytes,
            'duration_seconds' => $duration,
        ]);
    }

    /**
     * Calculate monthly usage across all devices owned by an entity.
     *
     * @return array{total_consumed_bytes: int, quota_mb: int, excess_mb: int}
     */
    public function calculateMonthlyUsage(string $ownerEntityId, string $period, int $quotaMb): array
    {
        $startOfMonth = CarbonImmutable::parse($period.'-01')->startOfMonth();
        $endOfMonth = $startOfMonth->endOfMonth();

        $cdrQuery = TelecomCdr::whereIn('device_id', function ($query) use ($ownerEntityId): void {
            $query->select('id')->from('tlx_iot_devices')->where('owner_entity_id', $ownerEntityId);
        })->whereBetween('started_at', [$startOfMonth, $endOfMonth]);

        $monthlyQuotaBytes = $quotaMb * UsageRater::BYTES_PER_MB;
        $totalCount = (clone $cdrQuery)->count();

        if ($totalCount <= 1000) {
            $ratedSessions = $cdrQuery
                ->select(['session_id', 'bytes_transferred'])
                ->get()
                ->map(fn ($cdr) => ['session_id' => $cdr->session_id, 'bytes' => $cdr->bytes_transferred])
                ->toArray();

            $rating = UsageRater::rate($ratedSessions, $monthlyQuotaBytes, 0, 0);

            return [
                'total_consumed_bytes' => $rating['total_rated_bytes'],
                'quota_mb' => $quotaMb,
                'excess_mb' => $rating['excess_mb'],
            ];
        }

        $totalRatedBytes = (int) $cdrQuery->sum('rated_bytes');
        $excessBytes = max(0, $totalRatedBytes - $monthlyQuotaBytes);
        $excessMb = ($excessBytes === 0) ? 0 : (int) intdiv($excessBytes + (UsageRater::BYTES_PER_MB - 1), UsageRater::BYTES_PER_MB);

        return [
            'total_consumed_bytes' => $totalRatedBytes,
            'quota_mb' => $quotaMb,
            'excess_mb' => $excessMb,
        ];
    }
}

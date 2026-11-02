<?php

declare(strict_types=1);

namespace Modules\Med\Application\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Modules\Med\Domain\Models\MedIpAsset;
use Modules\Med\Domain\Models\MedIpRightsShare;

class IpRightsRegistryService
{
    public const int TOTAL_BPS = 10000;

    /**
     * Register a new intellectual property asset.
     */
    public function registerAsset(array $params): MedIpAsset
    {
        if (empty($params['ip_code'])) {
            throw new InvalidArgumentException('IP Code is required.');
        }

        if (empty($params['title'])) {
            throw new InvalidArgumentException('Title is required.');
        }

        return MedIpAsset::firstOrCreate(
            ['ip_code' => $params['ip_code']],
            [
                'id' => (string) Str::uuid(),
                'title' => $params['title'],
                'ip_type' => $params['ip_type'] ?? 'SONG',
                'owner_entity_id' => $params['owner_entity_id'] ?? 'ENT-STUDIO-01',
                'status' => 'ACTIVE',
            ]
        );
    }

    /**
     * Record a new copyright share split with effective date.
     * Enforces that the sum of basis points across all rightsholders is exactly 10,000 bps.
     *
     * @return list<MedIpRightsShare>
     */
    public function setRightsShares(array $params): array
    {
        if (empty($params['ip_id'])) {
            throw new InvalidArgumentException('IP ID is required.');
        }

        if (empty($params['rights_type'])) {
            throw new InvalidArgumentException('Rights type is required.');
        }

        if (empty($params['effective_date'])) {
            throw new InvalidArgumentException('Effective date is required.');
        }

        $shares = $params['shares'] ?? [];
        if (empty($shares)) {
            throw new InvalidArgumentException('At least one rights share is required.');
        }

        $totalBps = 0;
        foreach ($shares as $s) {
            $bps = (int) ($s['share_bps'] ?? 0);
            if ($bps <= 0) {
                throw new InvalidArgumentException("Share basis points must be strictly positive, got: {$bps}");
            }
            $totalBps += $bps;
        }

        if ($totalBps !== self::TOTAL_BPS) {
            throw new InvalidArgumentException('Total basis points must equal '.self::TOTAL_BPS.", got: {$totalBps}");
        }

        $effectiveDate = CarbonImmutable::parse($params['effective_date'])->toDateString();

        $existing = MedIpRightsShare::where('ip_id', $params['ip_id'])
            ->where('rights_type', $params['rights_type'])
            ->whereDate('effective_date', $effectiveDate)
            ->exists();

        if ($existing) {
            throw new InvalidArgumentException("Rights shares for IP [{$params['ip_id']}] and date [{$effectiveDate}] already exist.");
        }

        return DB::transaction(function () use ($params, $shares, $effectiveDate) {
            $records = [];
            foreach ($shares as $s) {
                $records[] = MedIpRightsShare::create([
                    'id' => (string) Str::uuid(),
                    'ip_id' => $params['ip_id'],
                    'rights_type' => $params['rights_type'],
                    'rightsholder_party_id' => $s['rightsholder_party_id'],
                    'share_bps' => (int) $s['share_bps'],
                    'effective_date' => $effectiveDate,
                ]);
            }

            return $records;
        });
    }

    /**
     * Get active copyright share distribution at a specific historical or current date.
     *
     * @return array<string, int> Mapping of rightsholder_party_id => share_bps
     */
    public function getActiveSharesAt(string $ipId, string $rightsType, CarbonImmutable|string $date): array
    {
        $targetDate = is_string($date) ? CarbonImmutable::parse($date)->toDateString() : $date->toDateString();

        $latestEffectiveDate = MedIpRightsShare::where('ip_id', $ipId)
            ->where('rights_type', $rightsType)
            ->whereDate('effective_date', '<=', $targetDate)
            ->max('effective_date');

        if (! $latestEffectiveDate) {
            return [];
        }

        $latestDateString = CarbonImmutable::parse($latestEffectiveDate)->toDateString();

        return MedIpRightsShare::where('ip_id', $ipId)
            ->where('rights_type', $rightsType)
            ->whereDate('effective_date', $latestDateString)
            ->pluck('share_bps', 'rightsholder_party_id')
            ->toArray();
    }
}

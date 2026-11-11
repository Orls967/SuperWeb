<?php

declare(strict_types=1);

namespace Modules\Core\Application\Api;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Core\Domain\Models\CoreApiUsageEvent;
use Modules\Core\Domain\Models\CoreApiUsageRollup;

class ApiUsageRollupService
{
    /**
     * Roll up raw usage events for a given hour or default past 24 hours.
     *
     * @param string|null $specificHour Format: YYYY-MM-DD-HH
     * @return int Number of rollup records processed
     */
    public function rollup(?string $specificHour = null): int
    {
        $query = CoreApiUsageEvent::query()
            ->select([
                'api_client_id',
                DB::raw("strftime('%Y-%m-%d-%H', occurred_at) as bucket_hour"),
                DB::raw('COUNT(*) as total_requests'),
                DB::raw('SUM(CASE WHEN allowed = 1 THEN 1 ELSE 0 END) as allowed_requests'),
                DB::raw('SUM(CASE WHEN http_status = 429 THEN 1 ELSE 0 END) as throttled_requests'),
                DB::raw('SUM(CASE WHEN http_status >= 500 THEN 1 ELSE 0 END) as error_requests'),
            ])
            ->groupBy(['api_client_id', 'bucket_hour']);

        // Check if sqlite or mysql for date formatting
        $driver = DB::connection()->getDriverName();
        if ($driver === 'mysql') {
            $query = CoreApiUsageEvent::query()
                ->select([
                    'api_client_id',
                    DB::raw("DATE_FORMAT(occurred_at, '%Y-%m-%d-%H') as bucket_hour"),
                    DB::raw('COUNT(*) as total_requests'),
                    DB::raw('SUM(CASE WHEN allowed = 1 THEN 1 ELSE 0 END) as allowed_requests'),
                    DB::raw('SUM(CASE WHEN http_status = 429 THEN 1 ELSE 0 END) as throttled_requests'),
                    DB::raw('SUM(CASE WHEN http_status >= 500 THEN 1 ELSE 0 END) as error_requests'),
                ])
                ->groupBy(['api_client_id', 'bucket_hour']);
        }

        if ($specificHour !== null) {
            $query->having('bucket_hour', '=', $specificHour);
        }

        $records = $query->get();
        $processedCount = 0;

        foreach ($records as $row) {
            CoreApiUsageRollup::updateOrCreate(
                [
                    'api_client_id' => $row->api_client_id,
                    'bucket_hour' => (string) $row->bucket_hour,
                ],
                [
                    'total_requests' => (int) $row->total_requests,
                    'allowed_requests' => (int) $row->allowed_requests,
                    'throttled_requests' => (int) $row->throttled_requests,
                    'error_requests' => (int) $row->error_requests,
                ]
            );
            $processedCount++;
        }

        return $processedCount;
    }
}


<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Idempotent hourly rollup summary per B2B API client (api:usage-rollup).
 */
class CoreApiUsageRollup extends Model
{
    protected $table = 'core_api_usage_rollups';

    protected $fillable = [
        'api_client_id',
        'bucket_hour',
        'total_requests',
        'allowed_requests',
        'throttled_requests',
        'error_requests',
    ];

    protected function casts(): array
    {
        return [
            'api_client_id' => 'integer',
            'total_requests' => 'integer',
            'allowed_requests' => 'integer',
            'throttled_requests' => 'integer',
            'error_requests' => 'integer',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(CoreApiClient::class, 'api_client_id');
    }
}

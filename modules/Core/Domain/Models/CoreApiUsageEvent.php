<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Individual API request usage telemetry row for hourly rollup (55.5).
 */
class CoreApiUsageEvent extends Model
{
    public $timestamps = false;

    protected $table = 'core_api_usage_events';

    protected $fillable = [
        'api_client_id',
        'tenant_id',
        'route_uri',
        'http_method',
        'http_status',
        'allowed',
        'retry_after_ms',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'api_client_id' => 'integer',
            'http_status' => 'integer',
            'allowed' => 'boolean',
            'retry_after_ms' => 'integer',
            'occurred_at' => 'datetime',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(CoreApiClient::class, 'api_client_id');
    }
}

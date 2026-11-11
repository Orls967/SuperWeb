<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 24-hour persisted HTTP API v2 idempotency ledger record (W8-D46).
 */
class CoreApiIdempotencyRecord extends Model
{
    protected $table = 'core_api_idempotency_records';

    protected $fillable = [
        'tenant_id',
        'client_code',
        'route_name',
        'http_method',
        'idempotency_key',
        'request_hash',
        'response_status',
        'response_body',
        'headers',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'response_status' => 'integer',
            'headers' => 'array',
            'expires_at' => 'datetime',
        ];
    }
}

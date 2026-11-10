<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Model telemetri audit kueri pencarian (terproteksi anti-IDOR dan masking PII).
 */
class CoreSearchQueryLog extends Model
{
    protected $table = 'core_search_query_logs';

    protected $fillable = [
        'user_id',
        'user_role',
        'raw_query_hash',
        'redacted_query',
        'results_count',
        'execution_time_ms',
        'clicked_result_id',
    ];

    protected $casts = [
        'results_count' => 'integer',
        'execution_time_ms' => 'float',
    ];
}

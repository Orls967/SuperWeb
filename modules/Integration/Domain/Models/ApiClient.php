<?php

declare(strict_types=1);

namespace Modules\Integration\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class ApiClient extends Model
{
    protected $table = 'intg_api_clients';

    protected $guarded = [];

    protected $casts = [
        'rate_limit_per_minute' => 'integer',
        'is_active' => 'boolean',
    ];
}

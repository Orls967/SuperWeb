<?php

declare(strict_types=1);

namespace Modules\EnterpriseFinance\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class SodException extends Model
{
    use HasUuids;

    protected $table = 'ef_sod_exceptions';

    protected $fillable = [
        'id',
        'sod_rule_id',
        'user_id',
        'granted_by',
        'justification',
        'expires_at',
        'is_active',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'is_active' => 'boolean',
    ];
}


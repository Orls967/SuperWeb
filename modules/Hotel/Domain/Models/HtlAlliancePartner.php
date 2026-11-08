<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class HtlAlliancePartner extends Model
{
    use HasUuids;

    protected $table = 'htl_alliance_partners';

    protected $fillable = [
        'id',
        'partner_code',
        'partner_name',
        'category',
        'base_exchange_ratio',
        'transfer_fee_percentage',
        'min_points_transfer',
        'max_points_transfer_daily',
        'is_active',
        'api_endpoint',
    ];

    protected $casts = [
        'base_exchange_ratio' => 'float',
        'transfer_fee_percentage' => 'float',
        'min_points_transfer' => 'integer',
        'max_points_transfer_daily' => 'integer',
        'is_active' => 'boolean',
    ];
}

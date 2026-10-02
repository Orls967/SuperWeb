<?php

declare(strict_types=1);

namespace Modules\Logistics\Domain\Models;

class HsTariff extends LogisticsEntity
{
    protected $table = 'lgx_hs_tariffs';

    protected $fillable = [
        'hs_code',
        'description',
        'bm_bp',
        'ppn_bp',
        'pph22_api_bp',
        'pph22_non_api_bp',
        'requires_inspection',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'bm_bp' => 'integer',
            'ppn_bp' => 'integer',
            'pph22_api_bp' => 'integer',
            'pph22_non_api_bp' => 'integer',
            'requires_inspection' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}

<?php

declare(strict_types=1);

namespace Modules\Hospital\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class TariffVersion extends Model
{
    protected $table = 'hsp_tariff_versions';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'amount_idr' => 'integer',
            'effective_from' => 'date',
            'is_active' => 'boolean',
        ];
    }
}

<?php

declare(strict_types=1);

namespace Modules\Hospital\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class LabPartnerContract extends Model
{
    protected $table = 'hsp_lab_partner_contracts';

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'discount_percentage' => 'decimal:2',
            'max_turnaround_hours' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}

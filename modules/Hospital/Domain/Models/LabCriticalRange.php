<?php

declare(strict_types=1);

namespace Modules\Hospital\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class LabCriticalRange extends Model
{
    protected $table = 'hsp_lab_critical_ranges';

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'panic_low' => 'decimal:2',
            'panic_high' => 'decimal:2',
        ];
    }
}

<?php

declare(strict_types=1);

namespace Modules\Hcm\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionLaborAllocation extends Model
{
    use HasUuids;

    protected $table = 'hcm_production_labor_allocations';

    protected $fillable = [
        'payroll_id',
        'work_order_ref',
        'hours_worked',
        'allocated_cost_idr',
    ];

    protected $casts = [
        'hours_worked' => 'decimal:2',
        'allocated_cost_idr' => 'integer',
    ];

    public function payroll(): BelongsTo
    {
        return $this->belongsTo(Payroll::class, 'payroll_id');
    }
}

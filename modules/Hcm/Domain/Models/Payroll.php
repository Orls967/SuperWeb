<?php

declare(strict_types=1);

namespace Modules\Hcm\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payroll extends Model
{
    use HasUuids;

    protected $table = 'hcm_payrolls';

    protected $fillable = [
        'employee_id',
        'period',
        'gross_salary_idr',
        'deductions_idr',
        'net_salary_idr',
        'pph21_idr',
        'bpjs_tk_idr',
        'bpjs_kes_idr',
        'status',
    ];

    protected $casts = [
        'gross_salary_idr' => 'integer',
        'deductions_idr' => 'integer',
        'net_salary_idr' => 'integer',
        'pph21_idr' => 'integer',
        'bpjs_tk_idr' => 'integer',
        'bpjs_kes_idr' => 'integer',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function laborAllocations(): HasMany
    {
        return $this->hasMany(ProductionLaborAllocation::class, 'payroll_id');
    }
}

<?php

namespace Modules\Epc\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EpcProgressCertificate extends Model
{
    use HasUuids;

    protected $table = 'epc_progress_certificates';

    protected $fillable = [
        'project_id',
        'certificate_number',
        'period_month',
        'period_year',
        'certified_cumulative_progress_pct',
        'certified_incremental_progress_pct',
        'gross_claim_amount_idr',
        'retention_deduction_idr',
        'net_payable_amount_idr',
        'supervising_consultant_name',
        'certified_at',
        'status',
    ];

    protected $casts = [
        'period_month' => 'integer',
        'period_year' => 'integer',
        'certified_cumulative_progress_pct' => 'decimal:2',
        'certified_incremental_progress_pct' => 'decimal:2',
        'gross_claim_amount_idr' => 'integer',
        'retention_deduction_idr' => 'integer',
        'net_payable_amount_idr' => 'integer',
        'certified_at' => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(EpcProject::class, 'project_id');
    }
}

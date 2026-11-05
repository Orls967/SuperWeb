<?php

declare(strict_types=1);

namespace Modules\Syariah\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class SybRestructuringRequest extends Model
{
    protected $table = 'syb_restructuring_requests';

    protected $fillable = [
        'request_ref',
        'financing_ref',
        'customer_ref',
        'contract_type',
        'original_principal_minor',
        'original_margin_minor',
        'margin_haircut_bps',
        'revised_principal_minor',
        'revised_margin_minor',
        'total_restructured_debt_minor',
        'original_tenor_months',
        'new_tenor_months',
        'grace_period_months',
        'status',
        'reason',
        'requested_by',
        'dps_reviewer_ref',
        'dps_notes',
        'certificate_hash',
        'approved_at',
        'executed_at',
    ];

    protected function casts(): array
    {
        return [
            'original_principal_minor' => 'integer',
            'original_margin_minor' => 'integer',
            'margin_haircut_bps' => 'integer',
            'revised_principal_minor' => 'integer',
            'revised_margin_minor' => 'integer',
            'total_restructured_debt_minor' => 'integer',
            'original_tenor_months' => 'integer',
            'new_tenor_months' => 'integer',
            'grace_period_months' => 'integer',
            'approved_at' => 'datetime',
            'executed_at' => 'datetime',
        ];
    }

    public function installments(): HasMany
    {
        return $this->hasMany(SybRestructuredInstallment::class, 'request_ref', 'request_ref');
    }
}

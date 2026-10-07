<?php

namespace Modules\Hospital\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DataVaultAccess extends Model
{
    protected $table = 'hsp_data_vault_accesses';

    protected $fillable = [
        'request_code',
        'trial_id',
        'researcher_id',
        'purpose',
        'approved_by_irb',
        'irb_approval_hash',
        'status',
    ];

    protected $casts = [
        'approved_by_irb' => 'boolean',
    ];

    public function trial(): BelongsTo
    {
        return $this->belongsTo(ClinicalTrial::class, 'trial_id');
    }
}

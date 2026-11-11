<?php

declare(strict_types=1);

namespace Modules\EnterpriseFinance\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConsolidationEntry extends Model
{
    protected $table = 'ef_consolidation_entries';

    protected $fillable = [
        'consolidation_run_id',
        'entity_code',
        'canonical_account_code',
        'account_name',
        'local_debit_minor',
        'local_credit_minor',
        'elimination_debit_minor',
        'elimination_credit_minor',
        'consolidated_net_minor',
    ];

    protected $casts = [
        'consolidation_run_id' => 'integer',
        'local_debit_minor' => 'integer',
        'local_credit_minor' => 'integer',
        'elimination_debit_minor' => 'integer',
        'elimination_credit_minor' => 'integer',
        'consolidated_net_minor' => 'integer',
    ];

    public function run(): BelongsTo
    {
        return $this->belongsTo(ConsolidationRun::class, 'consolidation_run_id');
    }
}

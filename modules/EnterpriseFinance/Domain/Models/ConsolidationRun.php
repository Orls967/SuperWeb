<?php

declare(strict_types=1);

namespace Modules\EnterpriseFinance\Domain\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ConsolidationRun extends Model
{
    protected $table = 'ef_consolidation_runs';

    protected $fillable = [
        'run_code',
        'period',
        'reporting_currency',
        'total_entities_count',
        'total_debit_minor',
        'total_credit_minor',
        'elimination_total_minor',
        'is_balanced',
        'sha256_signature',
        'status',
        'executed_by',
    ];

    protected $casts = [
        'total_entities_count' => 'integer',
        'total_debit_minor' => 'integer',
        'total_credit_minor' => 'integer',
        'elimination_total_minor' => 'integer',
        'is_balanced' => 'boolean',
        'executed_by' => 'integer',
    ];

    public function entries(): HasMany
    {
        return $this->hasMany(ConsolidationEntry::class, 'consolidation_run_id');
    }

    public function executor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'executed_by');
    }
}

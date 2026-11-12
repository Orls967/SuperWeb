<?php

declare(strict_types=1);

namespace Modules\EnterpriseFinance\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class FinancialPeriodLock extends Model
{
    use HasUuids;

    protected $table = 'ef_financial_period_locks';

    protected $fillable = [
        'id',
        'entity_code',
        'period',
        'is_locked',
        'locked_at',
        'locked_by',
        'closing_voucher_ref',
        'subledger_recon_summary',
        'net_income_loss_minor',
    ];

    protected $casts = [
        'is_locked' => 'boolean',
        'locked_at' => 'datetime',
        'subledger_recon_summary' => 'array',
        'net_income_loss_minor' => 'integer',
    ];
}

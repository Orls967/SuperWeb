<?php

declare(strict_types=1);

namespace Modules\Insurance\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Insurance\Domain\Enums\SolvencyActionStatus;
use Modules\Insurance\Domain\Enums\SolvencyActionType;

class SolvencyAction extends Model
{
    protected $table = 'ins_solv_actions';

    /** @var list<string> */
    protected $fillable = [
        'snapshot_id',
        'action_type',
        'params',
        'simulated_ratio_bps',
        'status',
        'approval_ref',
        'ledger_transaction_id',
        'created_by',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'action_type' => SolvencyActionType::class,
            'status' => SolvencyActionStatus::class,
            'params' => 'array',
        ];
    }

    /** @return BelongsTo<SolvencySnapshot, $this> */
    public function snapshot(): BelongsTo
    {
        return $this->belongsTo(SolvencySnapshot::class, 'snapshot_id');
    }
}

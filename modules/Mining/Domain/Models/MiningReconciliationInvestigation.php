<?php

declare(strict_types=1);

namespace Modules\Mining\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class MiningReconciliationInvestigation extends Model
{
    protected $table = 'min_reconciliation_investigations';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'reconciliation_run_id',
        'investigation_code',
        'status',
        'resolution_notes',
        'resolved_by_user_id',
        'resolved_at',
    ];

    protected $casts = [
        'resolved_by_user_id' => 'integer',
        'resolved_at' => 'datetime',
    ];

    public function run()
    {
        return $this->belongsTo(MiningReconciliationRun::class, 'reconciliation_run_id');
    }
}

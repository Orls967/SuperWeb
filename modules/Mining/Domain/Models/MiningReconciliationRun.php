<?php

declare(strict_types=1);

namespace Modules\Mining\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class MiningReconciliationRun extends Model
{
    protected $table = 'min_reconciliation_runs';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'run_uuid',
        'site_id',
        'period_start',
        'period_end',
        'total_weighed_kg',
        'total_stockpile_in_kg',
        'total_stockpile_out_kg',
        'total_shipped_kg',
        'variance_kg',
        'variance_bps',
        'status',
    ];

    protected $casts = [
        'total_weighed_kg' => 'integer',
        'total_stockpile_in_kg' => 'integer',
        'total_stockpile_out_kg' => 'integer',
        'total_shipped_kg' => 'integer',
        'variance_kg' => 'integer',
        'variance_bps' => 'integer',
        'period_start' => 'datetime',
        'period_end' => 'datetime',
    ];

    public function investigations()
    {
        return $this->hasMany(MiningReconciliationInvestigation::class, 'reconciliation_run_id');
    }

    public function site()
    {
        return $this->belongsTo(MiningSite::class, 'site_id');
    }
}

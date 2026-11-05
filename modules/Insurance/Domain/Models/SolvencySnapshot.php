<?php

declare(strict_types=1);

namespace Modules\Insurance\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Insurance\Domain\Concerns\AppendOnly;
use Modules\Insurance\Domain\Enums\SolvencyAlertLevel;

class SolvencySnapshot extends Model
{
    use AppendOnly;

    protected $table = 'ins_solv_snapshots';

    public $timestamps = false;

    /** @var list<string> */
    protected $fillable = [
        'snapshot_key',
        'valuation_date',
        'parameter_versions',
        'risk_charges',
        'correlation',
        'operational_idr',
        'available_capital_idr',
        'rbc_idr',
        'ratio_bps',
        'alert_level',
        'input_hash',
        'created_by',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'valuation_date' => 'date',
            'parameter_versions' => 'array',
            'risk_charges' => 'array',
            'correlation' => 'array',
            'alert_level' => SolvencyAlertLevel::class,
        ];
    }

    /** @return HasMany<SolvencyAction, $this> */
    public function actions(): HasMany
    {
        return $this->hasMany(SolvencyAction::class, 'snapshot_id');
    }
}

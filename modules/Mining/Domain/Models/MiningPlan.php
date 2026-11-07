<?php

declare(strict_types=1);

namespace Modules\Mining\Domain\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Mining\Domain\Enums\MinePlanStatus;

class MiningPlan extends Model
{
    use HasUuids;

    protected $table = 'min_mine_plans';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'site_id',
        'plan_code',
        'period_month',
        'commodity',
        'target_cut_kg',
        'target_fill_kg',
        'target_ore_kg',
        'target_grade_ppm',
        'status',
        'version',
        'approved_by_user_id',
        'notes',
    ];

    protected $casts = [
        'target_cut_kg' => 'integer',
        'target_fill_kg' => 'integer',
        'target_ore_kg' => 'integer',
        'target_grade_ppm' => 'integer',
        'version' => 'integer',
        'status' => MinePlanStatus::class,
    ];

    public function site(): BelongsTo
    {
        return $this->belongsTo(MiningSite::class, 'site_id');
    }

    public function shifts(): HasMany
    {
        return $this->hasMany(MiningPlanShift::class, 'mine_plan_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }
}

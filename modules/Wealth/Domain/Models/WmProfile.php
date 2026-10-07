<?php

declare(strict_types=1);

namespace Modules\Wealth\Domain\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class WmProfile extends Model
{
    protected $table = 'wm_profiles';

    protected $fillable = [
        'user_id',
        'risk_profile',
        'emergency_fund_target_idr',
        'monthly_spend_baseline_idr',
        'auto_invest_enabled',
    ];

    protected $casts = [
        'emergency_fund_target_idr' => 'integer',
        'monthly_spend_baseline_idr' => 'integer',
        'auto_invest_enabled' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function plan()
    {
        return $this->hasOne(WmPlan::class, 'profile_id');
    }

    public function holdings()
    {
        return $this->hasMany(WmHolding::class, 'profile_id');
    }
}

<?php

declare(strict_types=1);

namespace Modules\CloudKitchen\Domain\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class CateringSubscription extends Model
{
    protected $table = 'resto_catering_subscriptions';

    protected $fillable = [
        'subscription_code',
        'user_id',
        'package_name',
        'daily_quota',
        'used_quota_today',
        'monthly_fee_idr',
        'daily_meal_price_idr',
        'starts_at',
        'expires_at',
        'status',
    ];

    protected $casts = [
        'daily_quota' => 'integer',
        'used_quota_today' => 'integer',
        'monthly_fee_idr' => 'integer',
        'daily_meal_price_idr' => 'integer',
        'starts_at' => 'date',
        'expires_at' => 'date',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function orders()
    {
        return $this->hasMany(CateringOrder::class, 'subscription_id');
    }
}

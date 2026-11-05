<?php

declare(strict_types=1);

namespace Modules\Syariah\Domain\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Syariah\Domain\Enums\WealthRiskProfile;

/**
 * Model Portofolio Investasi Syariah Nasabah (D162-3).
 */
final class SybWealthPortfolio extends Model
{
    protected $table = 'syb_wealth_portfolios';

    protected $fillable = [
        'user_id',
        'risk_profile',
        'current_balance_idr',
        'total_invested_idr',
        'total_purification_idr',
        'status',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'risk_profile' => WealthRiskProfile::class,
        'current_balance_idr' => 'integer',
        'total_invested_idr' => 'integer',
        'total_purification_idr' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(SybWealthOrder::class, 'portfolio_id');
    }
}


<?php

declare(strict_types=1);

namespace Modules\Resto\Domain\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Shared\Domain\Traits\HasUuid;

class OutletContract extends Model
{
    use HasUuid;

    protected $table = 'resto_outlet_contracts';

    protected $fillable = [
        'uuid',
        'outlet_id',
        'franchisee_user_id',
        'contract_number',
        'royalty_percent',
        'marketing_fee_percent',
        'fixed_monthly_management_fee',
        'valid_from',
        'valid_until',
        'is_active',
    ];

    protected $casts = [
        'royalty_percent' => 'string',
        'marketing_fee_percent' => 'string',
        'fixed_monthly_management_fee' => 'integer',
        'valid_from' => 'date',
        'valid_until' => 'date',
        'is_active' => 'boolean',
    ];

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class, 'outlet_id');
    }

    public function franchisee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'franchisee_user_id');
    }

    public function royaltyPostings(): HasMany
    {
        return $this->hasMany(RoyaltyPosting::class, 'contract_id');
    }
}

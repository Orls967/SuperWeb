<?php

declare(strict_types=1);

namespace Modules\Rwa\Domain\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RwaAsset extends Model
{
    protected $table = 'rwa_assets';

    protected $fillable = [
        'token_symbol',
        'name',
        'underlying_asset_type',
        'underlying_asset_id',
        'appraisal_document_ref',
        'appraisal_value_idr',
        'total_supply_tokens',
        'token_price_idr',
        'max_holder_concentration_pct',
        'status',
        'approved_by_primary_id',
        'approved_by_secondary_id',
        'approved_at',
    ];

    protected $casts = [
        'appraisal_value_idr' => 'integer',
        'total_supply_tokens' => 'integer',
        'token_price_idr' => 'integer',
        'max_holder_concentration_pct' => 'float',
        'approved_at' => 'datetime',
    ];

    public function holdings(): HasMany
    {
        return $this->hasMany(RwaHolding::class, 'rwa_asset_id');
    }

    public function offerings(): HasMany
    {
        return $this->hasMany(RwaOffering::class, 'rwa_asset_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(RwaOrderbookOrder::class, 'rwa_asset_id');
    }

    public function dividends(): HasMany
    {
        return $this->hasMany(RwaDividend::class, 'rwa_asset_id');
    }

    public function corporateActions(): HasMany
    {
        return $this->hasMany(RwaCorporateAction::class, 'rwa_asset_id');
    }

    public function primaryApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_primary_id');
    }

    public function secondaryApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_secondary_id');
    }
}

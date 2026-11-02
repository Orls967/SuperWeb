<?php

declare(strict_types=1);

namespace Modules\Ret\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Shared\Domain\Traits\HasUuid;

class RetSeller extends Model
{
    use HasUuid;

    protected $table = 'ret_sellers';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'party_id',
        'seller_name',
        'kyb_status',
        'commission_override_bps',
        'payout_account_ref',
        'map_violation_count',
        'status',
    ];

    protected $casts = [
        'commission_override_bps' => 'integer',
        'map_violation_count' => 'integer',
    ];

    public function listings(): HasMany
    {
        return $this->hasMany(RetListing::class, 'seller_id');
    }

    public function settlements(): HasMany
    {
        return $this->hasMany(RetSellerSettlement::class, 'seller_id');
    }
}

<?php

declare(strict_types=1);

namespace Modules\Partner\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CosellListing extends Model
{
    use HasUuids;

    protected $table = 'ptn_cosell_listings';

    protected $fillable = [
        'partner_id', 'title', 'category', 'price_idr', 'referral_fee_percent', 'status',
    ];

    protected $casts = [
        'price_idr' => 'integer',
        'referral_fee_percent' => 'float',
    ];

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class, 'partner_id');
    }
}

<?php

declare(strict_types=1);

namespace Modules\Syariah\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class SybHalalMerchant extends Model
{
    protected $table = 'syb_halal_merchants';

    protected $fillable = [
        'merchant_code',
        'name',
        'category_code',
        'line_code',
        'certificate_number',
        'issuing_authority',
        'valid_from',
        'expires_at',
        'status',
        'is_blacklisted',
        'blacklist_reason',
    ];

    protected function casts(): array
    {
        return [
            'valid_from' => 'date',
            'expires_at' => 'date',
            'is_blacklisted' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(SybHalalCategory::class, 'category_code', 'category_code');
    }
}

<?php

declare(strict_types=1);

namespace Modules\Pricing\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Daftar harga per segmen/saluran/wilayah/mata uang (44.1). */
class PriceList extends Model
{
    use HasUuids;

    protected $table = 'pric_price_lists';

    protected $fillable = [
        'code', 'name', 'channel', 'segment', 'region_code', 'currency', 'priority',
        'valid_from', 'valid_until', 'status', 'notes', 'created_by_user_id',
    ];

    protected $casts = [
        'priority' => 'integer', 'valid_from' => 'date', 'valid_until' => 'date',
        'created_by_user_id' => 'integer',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(PriceListItem::class, 'price_list_id');
    }

    public function isLive(?string $at = null): bool
    {
        $date = $at ?? now()->toDateString();

        return $this->status === 'active'
            && $this->valid_from->toDateString() <= $date
            && ($this->valid_until === null || $this->valid_until->toDateString() >= $date);
    }
}

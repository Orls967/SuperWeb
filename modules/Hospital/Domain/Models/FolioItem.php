<?php

declare(strict_types=1);

namespace Modules\Hospital\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FolioItem extends Model
{
    protected $table = 'hsp_folio_items';

    protected $fillable = [
        'billing_episode_id',
        'item_category',
        'description',
        'quantity',
        'unit_price_idr',
        'subtotal_idr',
    ];

    public function episode(): BelongsTo
    {
        return $this->belongsTo(BillingEpisode::class, 'billing_episode_id');
    }
}

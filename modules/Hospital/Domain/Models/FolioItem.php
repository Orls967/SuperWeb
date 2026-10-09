<?php

namespace Modules\Hospital\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FolioItem extends Model
{
    protected $table = 'hsp_folio_items';

    protected $guarded = [];

    public function episode(): BelongsTo
    {
        return $this->belongsTo(BillingEpisode::class, 'billing_episode_id');
    }
}

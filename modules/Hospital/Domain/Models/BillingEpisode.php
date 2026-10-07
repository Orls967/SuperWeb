<?php

namespace Modules\Hospital\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BillingEpisode extends Model
{
    protected $table = 'hsp_billing_episodes';

    protected $guarded = [];

    public function encounter(): BelongsTo
    {
        return $this->belongsTo(Encounter::class, 'encounter_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(FolioItem::class, 'billing_episode_id');
    }
}

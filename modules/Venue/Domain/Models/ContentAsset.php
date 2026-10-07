<?php

namespace Modules\Venue\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContentAsset extends Model
{
    protected $table = 'ven_content_assets';

    protected $fillable = [
        'asset_code',
        'creator_id',
        'title',
        'media_type',
        'content_hash',
        'license_type',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Creator::class, 'creator_id');
    }
}

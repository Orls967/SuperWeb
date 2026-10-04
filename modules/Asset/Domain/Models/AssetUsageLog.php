<?php

declare(strict_types=1);

namespace Modules\Asset\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetUsageLog extends Model
{
    protected $table = 'ast_usage_logs';

    protected $fillable = ['asset_id', 'logged_at', 'units', 'unit_type', 'source'];

    protected $casts = ['logged_at' => 'date', 'units' => 'integer'];

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'asset_id');
    }
}

<?php

declare(strict_types=1);

namespace Modules\Asset\Domain\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetStocktake extends Model
{
    protected $table = 'ast_stocktakes';

    protected $fillable = [
        'asset_id', 'cycle_id', 'result', 'scanned_by_user_id', 'note',
        'approval_id', 'adjustment_status', 'adjustment_amount_idr',
    ];

    protected $casts = [
        'cycle_id' => 'integer',
        'scanned_by_user_id' => 'integer',
        'adjustment_id' => 'integer',
        'adjustment_amount_idr' => 'integer',
    ];

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'asset_id');
    }

    public function scannedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'scanned_by_user_id');
    }
}

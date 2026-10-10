<?php

declare(strict_types=1);

namespace Modules\Asset\Domain\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetDisposal extends Model
{
    protected $table = 'ast_disposals';

    protected $fillable = [
        'asset_id', 'method', 'proceeds_idr', 'book_value_at_disposal_idr', 'gain_loss_idr',
        'reason', 'approval_id', 'approval_status', 'source_type', 'source_id',
        'disposed_at', 'requested_by_user_id',
    ];

    protected $casts = [
        'proceeds_idr' => 'integer', 'book_value_at_disposal_idr' => 'integer', 'gain_loss_idr' => 'integer',
        'approval_id' => 'string', 'source_id' => 'integer', 'disposed_at' => 'datetime',
        'requested_by_user_id' => 'integer',
    ];

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'asset_id');
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }
}

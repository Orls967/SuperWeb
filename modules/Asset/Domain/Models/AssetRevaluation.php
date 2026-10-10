<?php

declare(strict_types=1);

namespace Modules\Asset\Domain\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetRevaluation extends Model
{
    protected $table = 'ast_revaluations';

    protected $fillable = [
        'asset_id', 'kind', 'old_value_idr', 'new_value_idr', 'difference_idr',
        'reason', 'approval_id', 'approval_status', 'requested_by_user_id',
    ];

    protected $casts = [
        'old_value_idr' => 'integer', 'new_value_idr' => 'integer', 'difference_idr' => 'integer',
        'approval_id' => 'string', 'requested_by_user_id' => 'integer',
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

<?php

declare(strict_types=1);

namespace Modules\Mall\Domain\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Shared\Domain\Traits\HasUuid;

class PointBatch extends Model
{
    use HasUuid;

    protected $table = 'mall_point_batches';

    protected $fillable = [
        'uuid',
        'user_id',
        'points_earned',
        'points_remaining',
        'source_type',
        'source_id',
        'earned_at',
        'expires_at',
        'is_expired',
    ];

    protected $casts = [
        'points_earned' => 'integer',
        'points_remaining' => 'integer',
        'earned_at' => 'datetime',
        'expires_at' => 'datetime',
        'is_expired' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

<?php

declare(strict_types=1);

namespace Modules\Venue\Domain\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScalperBlacklistLog extends Model
{
    protected $table = 'ven_scalper_blacklist_logs';

    protected $fillable = [
        'user_id',
        'reason_code',
        'detected_velocity_bps',
        'ip_address',
        'device_fingerprint',
        'action_taken',
    ];

    protected $casts = [
        'detected_velocity_bps' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}

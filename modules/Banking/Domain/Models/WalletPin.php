<?php

declare(strict_types=1);

namespace Modules\Banking\Domain\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WalletPin extends Model
{
    protected $table = 'bank_wallet_pins';

    protected $fillable = [
        'user_id',
        'pin_hash',
        'failed_attempts',
        'locked_until',
    ];

    protected $casts = [
        'failed_attempts' => 'integer',
        'locked_until' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function isLocked(): bool
    {
        return $this->locked_until !== null && $this->locked_until->isFuture();
    }
}

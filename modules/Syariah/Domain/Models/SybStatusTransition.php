<?php

declare(strict_types=1);

namespace Modules\Syariah\Domain\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Audit log riwayat transisi state machine append-only (PR5).
 */
final class SybStatusTransition extends Model
{
    protected $table = 'syb_status_transitions';

    protected $fillable = [
        'entity_type',
        'entity_id',
        'from_status',
        'to_status',
        'actor_user_id',
        'reason',
    ];

    protected $casts = [
        'entity_id' => 'integer',
        'actor_user_id' => 'integer',
    ];

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}

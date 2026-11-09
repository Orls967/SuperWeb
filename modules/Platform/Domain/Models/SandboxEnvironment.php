<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SandboxEnvironment extends Model
{
    use HasFactory;

    protected $table = 'plt_sandboxes';

    protected $fillable = [
        'name',
        'slug',
        'tier',
        'status',
        'owner_id',
        'idempotency_key',
        'expires_at',
        'metadata',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function transitions(): HasMany
    {
        return $this->hasMany(SandboxTransition::class, 'sandbox_id');
    }
}


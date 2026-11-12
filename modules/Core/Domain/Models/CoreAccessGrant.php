<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Access Grant entity with 8-status lifecycle and sub-second cache versioning (W8-D55).
 */
class CoreAccessGrant extends Model
{
    protected $table = 'core_access_grants';

    protected $fillable = [
        'grant_code',
        'tenant_id',
        'user_id',
        'resource',
        'action',
        'status',
        'scope_dimensions',
        'valid_from',
        'valid_to',
        'revoked_at',
        'grant_version',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'scope_dimensions' => 'array',
            'valid_from' => 'datetime',
            'valid_to' => 'datetime',
            'revoked_at' => 'datetime',
            'grant_version' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(CoreTenant::class, 'tenant_id');
    }

    public function transitions(): HasMany
    {
        return $this->hasMany(CoreAccessGrantTransition::class, 'grant_id');
    }
}

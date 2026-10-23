<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CoreMobileDevice extends Model
{
    protected $table = 'core_mobile_devices';

    protected $fillable = [
        'device_uuid',
        'user_id',
        'role_scope',
        'app_version',
        'platform',
        'last_seen_at',
        'sync_cursor',
        'is_revoked',
    ];

    protected function casts(): array
    {
        return [
            'last_seen_at' => 'datetime',
            'sync_cursor' => 'integer',
            'is_revoked' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}

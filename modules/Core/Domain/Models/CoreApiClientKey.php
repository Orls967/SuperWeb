<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Hashed API credential supporting dual active keys during <= 7-day rotation (55.5).
 */
class CoreApiClientKey extends Model
{
    protected $table = 'core_api_client_keys';

    protected $fillable = [
        'api_client_id',
        'rotated_from_id',
        'created_by_user_id',
        'token_ref',
        'key_prefix',
        'key_digest',
        'abilities',
        'status',
        'expires_at',
        'revoked_at',
    ];

    protected $hidden = ['key_digest'];

    protected function casts(): array
    {
        return [
            'api_client_id' => 'integer',
            'rotated_from_id' => 'integer',
            'created_by_user_id' => 'integer',
            'token_ref' => 'integer',
            'abilities' => 'array',
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(CoreApiClient::class, 'api_client_id');
    }

    public function canAbility(string $ability): bool
    {
        $granted = (array) ($this->abilities ?? []);

        return in_array('*', $granted, true) || in_array($ability, $granted, true);
    }
}

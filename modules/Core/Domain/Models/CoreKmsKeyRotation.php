<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CoreKmsKeyRotation extends Model
{
    protected $table = 'core_kms_key_rotations';

    protected $fillable = [
        'key_alias',
        'key_version',
        'status',
        'encryption_algorithm',
        'key_fingerprint',
        'rotation_reason',
        'user_id',
        'rotated_at',
    ];

    protected function casts(): array
    {
        return [
            'key_version' => 'integer',
            'rotated_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}

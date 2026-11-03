<?php

declare(strict_types=1);

namespace Modules\Tlx\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

class TelecomCdr extends Model
{
    use HasUuids;

    protected $table = 'tlx_cdrs';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'device_id',
        'session_id',
        'started_at',
        'ended_at',
        'bytes_transferred',
        'rated_bytes',
        'duration_seconds',
    ];

    protected $casts = [
        'bytes_transferred' => 'integer',
        'rated_bytes' => 'integer',
        'duration_seconds' => 'integer',
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function () {
            throw new RuntimeException('TelecomCdr is append-only and cannot be updated.');
        });

        static::deleting(function () {
            throw new RuntimeException('TelecomCdr is append-only and cannot be deleted.');
        });
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(IotDevice::class, 'device_id');
    }
}

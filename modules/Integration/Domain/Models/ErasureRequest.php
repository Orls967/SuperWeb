<?php

declare(strict_types=1);

namespace Modules\Integration\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class ErasureRequest extends Model
{
    protected $table = 'sec_erasure_requests';

    protected $fillable = [
        'request_code',
        'subject_id',
        'status',
        'lines_to_erase',
        'erasure_report',
        'requested_at',
        'completed_at',
    ];

    protected $casts = [
        'lines_to_erase' => 'array',
        'erasure_report' => 'array',
        'requested_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public const STATUS_PENDING = 'PENDING';

    public const STATUS_IN_PROGRESS = 'IN_PROGRESS';

    public const STATUS_COMPLETED = 'COMPLETED';

    public const STATUS_PARTIAL = 'PARTIAL';

    public function isCompleted(): bool
    {
        return in_array($this->status, [self::STATUS_COMPLETED, self::STATUS_PARTIAL], true);
    }
}

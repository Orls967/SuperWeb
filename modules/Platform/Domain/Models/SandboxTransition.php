<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SandboxTransition extends Model
{
    public $timestamps = false;

    protected $table = 'plt_sandbox_transitions';

    protected $fillable = [
        'sandbox_id',
        'from_status',
        'to_status',
        'reason',
        'context',
        'created_at',
    ];

    protected $casts = [
        'context' => 'array',
        'created_at' => 'datetime',
    ];

    public function sandbox(): BelongsTo
    {
        return $this->belongsTo(SandboxEnvironment::class, 'sandbox_id');
    }
}


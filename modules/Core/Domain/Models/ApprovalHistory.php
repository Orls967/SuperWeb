<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApprovalHistory extends Model
{
    public $timestamps = false;

    protected $table = 'core_approval_histories';

    protected $fillable = [
        'approval_id',
        'step_number',
        'user_id',
        'action',
        'notes',
        'context',
        'created_at',
    ];

    protected $casts = [
        'step_number' => 'integer',
        'context' => 'array',
        'created_at' => 'datetime',
    ];

    public function approval(): BelongsTo
    {
        return $this->belongsTo(ApprovalRequest::class, 'approval_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}

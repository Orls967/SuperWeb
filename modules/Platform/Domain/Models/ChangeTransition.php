<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChangeTransition extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $table = 'plt_change_transitions';

    protected $fillable = [
        'change_request_id',
        'from_status',
        'to_status',
        'operator_id',
        'reason',
        'transition_metadata',
        'created_at',
    ];

    protected $casts = [
        'transition_metadata' => 'array',
        'created_at' => 'datetime',
    ];

    public function changeRequest(): BelongsTo
    {
        return $this->belongsTo(ChangeRequest::class, 'change_request_id');
    }

    public function operator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'operator_id');
    }
}

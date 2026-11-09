<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReleaseHotfix extends Model
{
    use HasFactory;

    protected $table = 'plt_hotfixes';

    protected $fillable = [
        'hotfix_number',
        'release_train_id',
        'change_request_id',
        'severity',
        'approved_by',
        'isolation_status',
    ];

    public function releaseTrain(): BelongsTo
    {
        return $this->belongsTo(ReleaseTrain::class, 'release_train_id');
    }

    public function changeRequest(): BelongsTo
    {
        return $this->belongsTo(ChangeRequest::class, 'change_request_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}

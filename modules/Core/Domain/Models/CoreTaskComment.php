<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Model komentar dan riwayat kolaborasi pada tugas kotak masuk.
 */
class CoreTaskComment extends Model
{
    protected $table = 'core_task_comments';

    protected $fillable = [
        'task_id',
        'user_id',
        'author_name',
        'comment',
        'attachments',
    ];

    protected $casts = [
        'attachments' => 'array',
    ];

    public function task(): BelongsTo
    {
        return $this->belongsTo(CoreTask::class, 'task_id', 'id');
    }
}

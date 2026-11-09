<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PostmortemRecord extends Model
{
    protected $table = 'plt_postmortems';

    protected $fillable = [
        'incident_ref',
        'severity',
        'title',
        'root_cause',
        'action_items',
        'started_at',
        'resolved_at',
        'author_id',
    ];

    protected $casts = [
        'action_items' => 'array',
        'started_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}


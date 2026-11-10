<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Model tugas tindakan kerja Kotak Masuk "Tugas Saya" (§19.1).
 */
class CoreTask extends Model
{
    use HasUuids;

    protected $table = 'core_tasks';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'title',
        'description',
        'module',
        'source_entity_type',
        'source_entity_id',
        'assignee_user_id',
        'assignee_role',
        'status',
        'priority',
        'due_at',
        'action_url',
        'payload',
        'escalation_level',
    ];

    protected $casts = [
        'payload' => 'array',
        'escalation_level' => 'integer',
        'due_at' => 'datetime',
    ];

    /**
     * Relasi ke riwayat mutasi status tugas.
     */
    public function histories(): HasMany
    {
        return $this->hasMany(CoreTaskHistory::class, 'task_id', 'id')->orderBy('created_at', 'asc');
    }

    /**
     * Relasi ke komentar kolaborasi internal tugas.
     */
    public function comments(): HasMany
    {
        return $this->hasMany(CoreTaskComment::class, 'task_id', 'id')->orderBy('created_at', 'asc');
    }
}

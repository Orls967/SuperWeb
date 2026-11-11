<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CoreRetentionPolicy extends Model
{
    protected $table = 'core_retention_policies';

    protected $fillable = [
        'module_name',
        'source_table',
        'archive_table',
        'retention_days',
        'native_partition_hint',
        'is_active',
    ];

    protected $casts = [
        'retention_days' => 'integer',
        'is_active' => 'boolean',
    ];

    public function runs(): HasMany
    {
        return $this->hasMany(CoreRetentionRun::class, 'retention_policy_id');
    }
}


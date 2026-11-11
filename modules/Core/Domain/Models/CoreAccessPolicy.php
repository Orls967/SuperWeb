<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * ABAC Access Policy definition (W8-D53, W8-D54).
 */
class CoreAccessPolicy extends Model
{
    protected $table = 'core_access_policies';

    protected $fillable = [
        'policy_code',
        'name',
        'target_resource',
        'effect',
        'dimensions',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'dimensions' => 'array',
            'is_active' => 'boolean',
        ];
    }
}

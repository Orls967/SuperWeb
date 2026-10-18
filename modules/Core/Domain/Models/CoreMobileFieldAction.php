<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class CoreMobileFieldAction extends Model
{
    protected $table = 'core_mobile_field_actions';

    protected $fillable = [
        'action_code',
        'role_scope',
        'action_type',
        'reference_id',
        'operator_name',
        'status',
        'details',
    ];

    protected function casts(): array
    {
        return [
            'details' => 'array',
        ];
    }
}

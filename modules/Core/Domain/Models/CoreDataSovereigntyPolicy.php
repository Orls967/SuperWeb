<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class CoreDataSovereigntyPolicy extends Model
{
    protected $table = 'core_data_sovereignty_policies';

    protected $fillable = [
        'jurisdiction_code',
        'regulation_reference',
        'mandatory_storage_region',
        'pii_encryption_required',
        'cross_border_transfer_allowed',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'pii_encryption_required' => 'boolean',
            'cross_border_transfer_allowed' => 'boolean',
        ];
    }
}

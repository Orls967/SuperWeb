<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class SyntheticDataset extends Model
{
    protected $table = 'plt_synth_datasets';

    protected $fillable = [
        'name',
        'domain',
        'record_count',
        'checksum',
        'schema_definition',
    ];

    protected $casts = [
        'schema_definition' => 'array',
    ];
}


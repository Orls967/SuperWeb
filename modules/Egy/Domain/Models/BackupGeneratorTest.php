<?php

declare(strict_types=1);

namespace Modules\Egy\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class BackupGeneratorTest extends Model
{
    protected $table = 'egy_backup_generator_tests';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $casts = [
        'scheduled_test_date' => 'datetime',
        'load_test_pct' => 'float',
        'runtime_minutes' => 'integer',
        'is_passed' => 'boolean',
    ];
}

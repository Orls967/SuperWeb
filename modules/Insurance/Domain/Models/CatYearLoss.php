<?php

declare(strict_types=1);

namespace Modules\Insurance\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Insurance\Domain\Concerns\AppendOnly;

class CatYearLoss extends Model
{
    use AppendOnly;

    protected $table = 'ins_cat_year_losses';

    public $timestamps = false;

    /** @var list<string> */
    protected $fillable = [
        'run_id',
        'year_index',
        'aggregate_idr',
        'max_event_idr',
        'events',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'events' => 'array',
        ];
    }
}

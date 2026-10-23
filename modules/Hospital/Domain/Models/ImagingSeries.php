<?php

declare(strict_types=1);

namespace Modules\Hospital\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImagingSeries extends Model
{
    protected $table = 'hsp_imaging_series';

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'slice_count' => 'integer',
        ];
    }

    public function study(): BelongsTo
    {
        return $this->belongsTo(ImagingStudy::class, 'study_id');
    }
}

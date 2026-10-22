<?php

declare(strict_types=1);

namespace Modules\Hospital\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LabCustodyTransfer extends Model
{
    protected $table = 'hsp_lab_custody_transfers';

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'hop_number' => 'integer',
            'transferred_at' => 'datetime',
        ];
    }

    public function specimen(): BelongsTo
    {
        return $this->belongsTo(LabSpecimen::class, 'specimen_id');
    }
}

<?php

declare(strict_types=1);

namespace Modules\Procurement\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Inspection extends Model
{
    protected $table = 'prc_inspections';

    protected $fillable = [
        'grn_id', 'receiving_line_id', 'result', 'quarantine', 'findings',
        'inspected_by_user_id',
    ];

    protected $casts = ['receiving_line_id' => 'integer', 'quarantine' => 'boolean'];

    public function grn(): BelongsTo
    {
        return $this->belongsTo(ReceivingReport::class, 'grn_id');
    }
}

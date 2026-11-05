<?php

declare(strict_types=1);

namespace Modules\Insurance\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Insurance\Domain\Concerns\AppendOnly;

class ReinsuranceBordereauLine extends Model
{
    use AppendOnly;

    protected $table = 'ins_rein_bordereau_lines';

    public $timestamps = false;

    /** @var list<string> */
    protected $fillable = [
        'bordereau_id',
        'cession_line_id',
        'amount_idr',
        'commission_idr',
        'reinstatement_idr',
    ];

    /** @return BelongsTo<CessionLine, $this> */
    public function cessionLine(): BelongsTo
    {
        return $this->belongsTo(CessionLine::class, 'cession_line_id');
    }
}

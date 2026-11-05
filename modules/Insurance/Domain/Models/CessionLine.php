<?php

declare(strict_types=1);

namespace Modules\Insurance\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Insurance\Domain\Concerns\AppendOnly;
use Modules\Party\Contracts\Party;

class CessionLine extends Model
{
    use AppendOnly;

    protected $table = 'ins_cession_lines';

    public $timestamps = false;

    /** @var list<string> */
    protected $fillable = [
        'event_id',
        'treaty_id',
        'layer_code',
        'reinsurer_party_id',
        'ceded_idr',
        'commission_idr',
        'reinstatement_idr',
    ];

    /** @return BelongsTo<CessionEvent, $this> */
    public function event(): BelongsTo
    {
        return $this->belongsTo(CessionEvent::class, 'event_id');
    }

    /** @return BelongsTo<ReinsuranceTreaty, $this> */
    public function treaty(): BelongsTo
    {
        return $this->belongsTo(ReinsuranceTreaty::class, 'treaty_id');
    }

    /** @return BelongsTo<Party, $this> */
    public function reinsurer(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'reinsurer_party_id');
    }
}

<?php

declare(strict_types=1);

namespace Modules\Insurance\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Party\Contracts\Party;

class ReinsuranceTreatyPanel extends Model
{
    protected $table = 'ins_rein_treaty_panel';

    /** @var list<string> */
    protected $fillable = [
        'treaty_id',
        'reinsurer_party_id',
        'share_bps',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'share_bps' => 'integer',
        ];
    }

    /** @return BelongsTo<Party, $this> */
    public function reinsurer(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'reinsurer_party_id');
    }

    /** @return BelongsTo<ReinsuranceTreaty, $this> */
    public function treaty(): BelongsTo
    {
        return $this->belongsTo(ReinsuranceTreaty::class, 'treaty_id');
    }
}

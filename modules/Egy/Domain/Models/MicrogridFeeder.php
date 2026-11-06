<?php

declare(strict_types=1);

namespace Modules\Egy\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Model Feeder Penyulang Beban Microgrid.
 *
 * @property int $id
 * @property int $microgrid_id
 * @property string $feeder_code
 * @property string $feeder_name
 * @property string $tier
 * @property int $priority_rank
 * @property int $rated_load_kw
 * @property bool $is_energized
 */
final class MicrogridFeeder extends Model
{
    protected $table = 'egy_microgrid_feeders';

    protected $fillable = [
        'microgrid_id',
        'feeder_code',
        'feeder_name',
        'tier',
        'priority_rank',
        'rated_load_kw',
        'is_energized',
    ];

    protected $casts = [
        'microgrid_id' => 'integer',
        'priority_rank' => 'integer',
        'rated_load_kw' => 'integer',
        'is_energized' => 'boolean',
    ];

    /**
     * @return BelongsTo<Microgrid, $this>
     */
    public function microgrid(): BelongsTo
    {
        return $this->belongsTo(Microgrid::class, 'microgrid_id');
    }
}

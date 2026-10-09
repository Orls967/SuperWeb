<?php

declare(strict_types=1);

namespace Modules\Contract\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Poin data indeks harga tersimpan untuk eskalasi kontrak — 29.3.
 *
 * Data bersifat simulasi (sumber disimpan di kolom `source`).
 */
class EscalationIndex extends Model
{
    use HasUuids;

    protected $table = 'ctr_escalation_indexes';

    protected $fillable = ['code', 'name', 'value', 'observed_at', 'source', 'meta'];

    protected $casts = [
        'value' => 'float',
        'observed_at' => 'date',
        'meta' => 'array',
    ];

    /**
     * Ambil nilai indeks terbaru (atau per tanggal yang diminta).
     */
    public static function latestValue(string $code, ?string $asOfDate = null): ?float
    {
        $query = static::where('code', $code)->orderByDesc('observed_at');

        if ($asOfDate !== null) {
            $query->whereDate('observed_at', '<=', $asOfDate);
        }

        $index = $query->first();

        return $index === null ? null : (float) $index->value;
    }
}

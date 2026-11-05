<?php

declare(strict_types=1);

namespace Modules\Syariah\Domain\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Parameter syariah dan regulasi perbankan/pasar modal syariah berversi (G7-D6, S1).
 */
final class SybParameter extends Model
{
    protected $table = 'syb_parameters';

    protected $fillable = [
        'code',
        'version',
        'effective_from',
        'source',
        'simulation_label',
        'value',
        'is_fixture',
        'owner_confirmed_at',
        'created_by',
    ];

    protected $casts = [
        'version' => 'integer',
        'effective_from' => 'date',
        'value' => 'array',
        'is_fixture' => 'boolean',
        'owner_confirmed_at' => 'datetime',
        'created_by' => 'integer',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public static function getLatestValue(string $code, array $default = []): array
    {
        /** @var self|null $param */
        $param = self::query()
            ->where('code', $code)
            ->orderByDesc('version')
            ->first();

        return $param ? (array) $param->value : $default;
    }
}


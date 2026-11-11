<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Models;

use DomainException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CoreEdiTransition extends Model
{
    protected $table = 'core_edi_transitions';

    protected $fillable = [
        'edi_interchange_id',
        'actor_user_id',
        'from_status',
        'to_status',
        'reason',
        'handler_module',
        'transitioned_at',
    ];

    protected $casts = [
        'transitioned_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(static function (): void {
            throw new DomainException('EDI_TRANSITION_IMMUTABLE: Riwayat transisi EDI bersifat append-only dan dilarang diubah.');
        });

        static::deleting(static function (): void {
            throw new DomainException('EDI_TRANSITION_IMMUTABLE: Riwayat transisi EDI bersifat append-only dan dilarang dihapus.');
        });
    }

    public function interchange(): BelongsTo
    {
        return $this->belongsTo(CoreEdiInterchange::class, 'edi_interchange_id');
    }
}

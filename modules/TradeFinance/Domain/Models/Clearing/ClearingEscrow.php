<?php

declare(strict_types=1);

namespace Modules\TradeFinance\Domain\Models\Clearing;

use Illuminate\Database\Eloquent\Model;
use Modules\TradeFinance\Domain\Models\LetterOfCredit;
use RuntimeException;

class ClearingEscrow extends Model
{
    public const STATUS_DEPOSITED = 'DEPOSITED';

    public const STATUS_RELEASED = 'RELEASED';

    /** Kolom fakta setoran: tidak boleh berubah setelah dibuat (PR5). */
    private const IMMUTABLE_FACTS = [
        'escrow_code', 'letter_of_credit_id', 'importer_user_id', 'exporter_user_id',
        'usdt_micro', 'deposit_fx_rate_idr', 'deposit_idempotency_key', 'deposited_at',
    ];

    protected $table = 'tf_clearing_escrows';

    protected $fillable = [
        'escrow_code', 'letter_of_credit_id', 'importer_user_id', 'exporter_user_id', 'usdt_micro',
        'deposit_fx_rate_idr', 'status', 'deposit_idempotency_key', 'deposited_at',
        'release_document_type', 'release_document_hash', 'custody_shipment_ref', 'custody_event_ref',
        'release_fx_rate_idr', 'fx_revaluation_idr', 'released_by_user_id', 'release_idempotency_key', 'released_at',
    ];

    protected $casts = [
        'usdt_micro' => 'integer',
        'deposit_fx_rate_idr' => 'integer',
        'release_fx_rate_idr' => 'integer',
        'fx_revaluation_idr' => 'integer',
        'deposited_at' => 'datetime',
        'released_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function (self $escrow) {
            foreach (self::IMMUTABLE_FACTS as $column) {
                if ($escrow->isDirty($column)) {
                    throw new RuntimeException("Kolom fakta escrow '{$column}' bersifat append-only dan tidak boleh diubah.");
                }
            }
            if ($escrow->getOriginal('status') === self::STATUS_RELEASED) {
                throw new RuntimeException('Escrow yang sudah dirilis tidak boleh diubah.');
            }
        });

        static::deleting(function () {
            throw new RuntimeException('Escrow clearing tidak boleh dihapus; koreksi lewat entri pembalik.');
        });
    }

    public function letterOfCredit()
    {
        return $this->belongsTo(LetterOfCredit::class, 'letter_of_credit_id');
    }

    public function isParty(int $userId): bool
    {
        return $userId === (int) $this->importer_user_id || $userId === (int) $this->exporter_user_id;
    }

    public function ledgerAccountCode(): string
    {
        return "tf:escrow:{$this->letter_of_credit_id}:USDT";
    }
}

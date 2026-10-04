<?php

declare(strict_types=1);

namespace Modules\Contract\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Contract\Domain\Enums\PaymentScheduleKind;
use Modules\Contract\Domain\Enums\PaymentScheduleStatus;

/**
 * Satu termin/jadwal bayar kontrak (29.1).
 *
 * Uang = integer IDR. `amount_idr` nilai termin sebelum retensi,
 * `retention_amount_idr` potongan retensi pada termin tersebut.
 */
class PaymentSchedule extends Model
{
    use HasUuids;

    protected $table = 'ctr_payment_schedules';

    protected $fillable = [
        'contract_id', 'milestone_id', 'kind', 'due_date',
        'amount_idr', 'retention_amount_idr', 'paid_amount_idr',
        'status', 'notes', 'paid_at',
    ];

    protected $casts = [
        'kind' => PaymentScheduleKind::class,
        'status' => PaymentScheduleStatus::class,
        'due_date' => 'date',
        'amount_idr' => 'integer',
        'retention_amount_idr' => 'integer',
        'paid_amount_idr' => 'integer',
        'paid_at' => 'datetime',
    ];

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class, 'contract_id');
    }

    public function milestone(): BelongsTo
    {
        return $this->belongsTo(ContractMilestone::class, 'milestone_id');
    }

    /**
     * Nilai yang benar-benar diterima setelah retensi.
     */
    public function netAmount(): int
    {
        return (int) $this->amount_idr - (int) $this->retention_amount_idr;
    }

    public function balanceDue(): int
    {
        return (int) $this->amount_idr - (int) $this->paid_amount_idr;
    }

    public function markPaid(int $amountIdr): void
    {
        $newPaid = (int) $this->paid_amount_idr + $amountIdr;

        $this->paid_amount_idr = $newPaid;
        $this->status = match (true) {
            $newPaid >= (int) $this->amount_idr => PaymentScheduleStatus::Paid,
            $newPaid > 0 => PaymentScheduleStatus::Partial,
            default => PaymentScheduleStatus::Pending,
        };

        if ($this->status === PaymentScheduleStatus::Paid) {
            $this->paid_at = now();
        }

        $this->save();
    }
}

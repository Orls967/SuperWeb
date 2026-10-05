<?php

declare(strict_types=1);

namespace Modules\Distribution\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/** Piutang distributor (subledger dist:ar). */
class ArInvoice extends Model
{
    use HasUuids;

    protected $table = 'dist_ar_invoices';

    protected $fillable = [
        'number', 'distributor_id', 'source_type', 'source_ref', 'amount_idr',
        'paid_amount_idr', 'denda_idr', 'status', 'invoice_date', 'due_date',
        'notes', 'created_by_user_id',
    ];

    protected $casts = [
        'amount_idr' => 'integer', 'paid_amount_idr' => 'integer', 'denda_idr' => 'integer',
        'invoice_date' => 'date', 'due_date' => 'date', 'created_by_user_id' => 'integer',
    ];

    public function distributor(): BelongsTo
    {
        return $this->belongsTo(Distributor::class, 'distributor_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(ArPayment::class, 'invoice_id');
    }

    public function openAmount(): int
    {
        return max(0, (int) $this->amount_idr + (int) $this->denda_idr - (int) $this->paid_amount_idr);
    }

    public function isOverdue(?string $at = null): bool
    {
        return $this->due_date !== null
            && $this->status !== 'paid'
            && $this->due_date->lt($at ?? now()->toDateString());
    }

    /** Hari terlambat untuk perhitungan denda. */
    public function overdueDays(?string $at = null): int
    {
        if (! $this->isOverdue($at)) {
            return 0;
        }

        return (int) $this->due_date->diffInDays(Carbon::parse($at ?? now()->toDateString()));
    }
}

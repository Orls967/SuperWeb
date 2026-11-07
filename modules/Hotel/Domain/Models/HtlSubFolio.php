<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;

class HtlSubFolio extends Model
{
    use HasUuids;

    protected $table = 'htl_sub_folios';

    protected $fillable = [
        'id',
        'folio_id',
        'payer_type',
        'corporate_id',
        'total_charges_idr',
        'total_payments_idr',
        'balance_idr',
        'status',
    ];

    protected $casts = [
        'total_charges_idr' => 'integer',
        'total_payments_idr' => 'integer',
        'balance_idr' => 'integer',
    ];

    public function folio(): BelongsTo
    {
        return $this->belongsTo(HotelFolio::class, 'folio_id');
    }

    public function corporateAccount(): BelongsTo
    {
        return $this->belongsTo(HtlCorporateAccount::class, 'corporate_id');
    }

    public function addCharge(int $amountIdr): void
    {
        if ($amountIdr < 0) {
            throw new InvalidArgumentException('Charge amount cannot be negative.');
        }

        $this->total_charges_idr += $amountIdr;
        $this->balance_idr = $this->total_charges_idr - $this->total_payments_idr;
        $this->save();
    }

    public function recordPayment(int $amountIdr): void
    {
        if ($amountIdr <= 0) {
            throw new InvalidArgumentException('Payment amount must be greater than zero.');
        }

        $this->total_payments_idr += $amountIdr;
        $this->balance_idr = $this->total_charges_idr - $this->total_payments_idr;

        if ($this->balance_idr <= 0) {
            $this->status = 'SETTLED';
        }

        $this->save();
    }
}

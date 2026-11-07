<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use InvalidArgumentException;

class HtlCorporateAccount extends Model
{
    use HasUuids;

    protected $table = 'htl_corporate_accounts';

    protected $fillable = [
        'id',
        'company_name',
        'tax_id_npwp',
        'billing_email',
        'credit_limit_idr',
        'current_outstanding_idr',
        'status',
    ];

    protected $casts = [
        'tax_id_npwp' => 'encrypted',
        'credit_limit_idr' => 'integer',
        'current_outstanding_idr' => 'integer',
    ];

    public function subFolios(): HasMany
    {
        return $this->hasMany(HtlSubFolio::class, 'corporate_id');
    }

    public function canCharge(int $amountIdr): bool
    {
        if ($this->status !== 'ACTIVE') {
            return false;
        }

        return ($this->current_outstanding_idr + $amountIdr) <= $this->credit_limit_idr;
    }

    public function chargeCredit(int $amountIdr): void
    {
        if (! $this->canCharge($amountIdr)) {
            throw new InvalidArgumentException(
                "Corporate credit limit exceeded for {$this->company_name}. Limit: {$this->credit_limit_idr}, Requested: {$amountIdr}"
            );
        }

        $this->current_outstanding_idr += $amountIdr;
        $this->save();
    }

    public function settleCredit(int $amountIdr): void
    {
        if ($amountIdr <= 0) {
            throw new InvalidArgumentException('Settlement amount must be greater than zero.');
        }

        $this->current_outstanding_idr = max(0, $this->current_outstanding_idr - $amountIdr);
        $this->save();
    }
}

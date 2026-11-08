<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HtlAllianceTransferLog extends Model
{
    use HasUuids;

    protected $table = 'htl_alliance_transfer_logs';

    protected $fillable = [
        'id',
        'travel_pass_id',
        'partner_code',
        'member_partner_account',
        'points_debited',
        'partner_units_credited',
        'transfer_fee_idr',
        'net_payable_idr',
        'status',
        'saga_reference',
        'idempotency_key',
        'certificate_hash',
    ];

    protected $casts = [
        'member_partner_account' => 'encrypted',
        'points_debited' => 'integer',
        'partner_units_credited' => 'integer',
        'transfer_fee_idr' => 'integer',
        'net_payable_idr' => 'integer',
    ];

    public function travelPass(): BelongsTo
    {
        return $this->belongsTo(HtlTravelPass::class, 'travel_pass_id');
    }
}

<?php

declare(strict_types=1);

namespace Modules\Contract\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Contract\Domain\Enums\ContractPartyRole;
use Modules\Party\Domain\Models\Party;

class ContractParty extends Model
{
    protected $table = 'ctr_contract_parties';

    protected $fillable = [
        'contract_id', 'party_id', 'role', 'signing_order',
        'is_signed', 'signed_at', 'signature_hash', 'signer_name', 'signer_title',
    ];

    protected $casts = [
        'role' => ContractPartyRole::class,
        'signing_order' => 'integer',
        'is_signed' => 'boolean',
        'signed_at' => 'datetime',
    ];

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class, 'contract_id');
    }

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'party_id');
    }
}

<?php

declare(strict_types=1);

namespace Modules\Contract\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Domain\Models\DocumentAttachment;
use Modules\Party\Domain\Models\LegalEntity;

/**
 * Tautan lampiran kontrak ke dokumen tersimpan (Core DocumentStore).
 *
 * Berkas, checksum, dan retensi hidup di core_documents; model ini hanya
 * mengikatnya ke kontrak beserta pihak penandatangan dan entitas hukum.
 */
class ContractAttachment extends Model
{
    protected $table = 'ctr_contract_attachments';

    protected $fillable = [
        'contract_id',
        'document_id',
        'contract_party_id',
        'legal_entity_id',
        'label',
        'kind',
    ];

    protected $casts = [
        'document_id' => 'integer',
    ];

    public const KINDS = ['signed_copy', 'annex', 'supporting', 'kyc'];

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class, 'contract_id');
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(DocumentAttachment::class, 'document_id');
    }

    public function signatory(): BelongsTo
    {
        return $this->belongsTo(ContractParty::class, 'contract_party_id');
    }

    public function legalEntity(): BelongsTo
    {
        return $this->belongsTo(LegalEntity::class, 'legal_entity_id');
    }
}

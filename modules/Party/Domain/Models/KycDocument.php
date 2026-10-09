<?php

declare(strict_types=1);

namespace Modules\Party\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Party\Domain\Enums\KycDocumentStatus;
use Modules\Party\Domain\Enums\KycDocumentType;

class KycDocument extends Model
{
    use HasUuids;

    protected $table = 'pty_kyc_documents';

    protected $fillable = [
        'party_id', 'document_type', 'document_number', 'document_number_hash',
        'issuer', 'issued_at', 'expires_at', 'status', 'approval_id',
        'rejection_reason', 'file_path', 'file_checksum', 'reminder_sent',
    ];

    protected $casts = [
        'document_type' => KycDocumentType::class,
        'status' => KycDocumentStatus::class,
        'issued_at' => 'date',
        'expires_at' => 'date',
        'reminder_sent' => 'boolean',
    ];

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'party_id');
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function expiresWithinDays(int $days): bool
    {
        return $this->expires_at
            && ! $this->isExpired()
            && $this->expires_at->diffInDays(now()) <= $days;
    }
}

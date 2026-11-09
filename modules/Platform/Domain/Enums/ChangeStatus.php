<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Enums;

enum ChangeStatus: string
{
    case DRAFT = 'draft';
    case ASSESSED = 'assessed';
    case CAB_REVIEW = 'cab_review';
    case APPROVED = 'approved';
    case SCHEDULED = 'scheduled';
    case DEPLOYING = 'deploying';
    case VERIFIED = 'verified';
    case ROLLED_BACK = 'rolled_back';
    case CLOSED = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Konsep (Draft)',
            self::ASSESSED => 'Dinilai (Assessed)',
            self::CAB_REVIEW => 'Tinjauan CAB (CAB Review)',
            self::APPROVED => 'Disetujui (Approved)',
            self::SCHEDULED => 'Terjadwal (Scheduled)',
            self::DEPLOYING => 'Sedang Deploy (Deploying)',
            self::VERIFIED => 'Terverifikasi (Verified)',
            self::ROLLED_BACK => 'Dipulihkan (Rolled Back)',
            self::CLOSED => 'Ditutup (Closed)',
        };
    }

    public function isTerminal(): bool
    {
        return $this === self::CLOSED || $this === self::ROLLED_BACK;
    }
}

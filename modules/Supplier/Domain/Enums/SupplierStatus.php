<?php

declare(strict_types=1);

namespace Modules\Supplier\Domain\Enums;

enum SupplierStatus: string
{
    case Candidate = 'candidate';
    case Approved = 'approved';
    case Preferred = 'preferred';
    case Probation = 'probation';
    case Disqualified = 'disqualified';

    public function label(): string
    {
        return match ($this) {
            self::Candidate => 'Kandidat',
            self::Approved => 'Disetujui',
            self::Preferred => 'Preferred',
            self::Probation => 'Probation',
            self::Disqualified => 'Didiskualifikasi',
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return match ($this) {
            self::Candidate => in_array($next, [self::Approved, self::Disqualified], true),
            self::Approved => in_array($next, [self::Preferred, self::Probation, self::Disqualified], true),
            self::Preferred => in_array($next, [self::Probation, self::Disqualified], true),
            self::Probation => in_array($next, [self::Approved, self::Preferred, self::Disqualified], true),
            self::Disqualified => $next === self::Candidate,
        };
    }
}

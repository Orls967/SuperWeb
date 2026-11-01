<?php

declare(strict_types=1);

namespace Modules\Edu\Application\Services;

use Carbon\CarbonImmutable;
use Modules\Core\Contracts\ClockInterface;
use Modules\Edu\Contracts\CredentialVerifier;
use Modules\Edu\Domain\Models\EduCertificate;

final class DefaultCredentialVerifier implements CredentialVerifier
{
    public function __construct(
        protected ClockInterface $clock
    ) {}

    /**
     * Checks if a user has an active, valid credential certificate at given point in time.
     */
    public function hasValid(int|string $userId, string $credentialCode, ?CarbonImmutable $at = null): bool
    {
        $timestamp = $at ?? CarbonImmutable::instance($this->clock->{'now'}());

        return EduCertificate::where(function ($query) use ($userId) {
            $query->where('student_user_id', $userId)
                ->orWhere('student_party_id', (string) $userId)
                ->orWhere('student_party_id', "student-{$userId}");
        })
            ->where(function ($query) use ($credentialCode) {
                $query->where('skill_competency_code', $credentialCode)
                    ->orWhereHas('program', function ($pq) use ($credentialCode) {
                        $pq->where('program_code', $credentialCode);
                    });
            })
            ->where('status', 'ACTIVE')
            ->where('issued_at', '<=', $timestamp)
            ->where('expires_at', '>=', $timestamp)
            ->exists();
    }
}

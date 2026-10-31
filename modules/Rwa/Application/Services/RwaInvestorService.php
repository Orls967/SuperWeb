<?php

declare(strict_types=1);

namespace Modules\Rwa\Application\Services;

use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Modules\Core\Contracts\SimClockInterface;
use Modules\Party\Contracts\InvestorKycLookupInterface;
use Modules\Rwa\Domain\Models\RwaInvestorVerification;

class RwaInvestorService
{
    public function __construct(
        protected SimClockInterface $simClock,
        protected ?InvestorKycLookupInterface $kycLookup = null
    ) {}

    public function verifyInvestor(
        int $userId,
        string $kycDocumentId,
        string $approvalId,
        DateTimeInterface|string $validUntil,
        string $idempotencyKey
    ): RwaInvestorVerification {
        if (trim($idempotencyKey) === '') {
            throw new \InvalidArgumentException('Idempotency key is required.');
        }

        $payloadHash = hash('sha256', json_encode([$userId, $kycDocumentId, $approvalId]));

        $existing = RwaInvestorVerification::where('idempotency_key', $idempotencyKey)->first();
        if ($existing) {
            if ($existing->payload_hash !== $payloadHash) {
                throw new \DomainException("Idempotency conflict: key '{$idempotencyKey}' was used with different payload.");
            }

            return $existing;
        }

        $now = $this->simClock->now();

        return DB::transaction(function () use ($userId, $kycDocumentId, $approvalId, $validUntil, $idempotencyKey, $payloadHash, $now) {
            return RwaInvestorVerification::updateOrCreate(
                ['user_id' => $userId],
                [
                    'kyc_document_ref' => $kycDocumentId,
                    'approval_ref' => $approvalId,
                    'status' => 'VERIFIED',
                    'idempotency_key' => $idempotencyKey,
                    'payload_hash' => $payloadHash,
                    'verified_at' => $now,
                    'expires_at' => $validUntil,
                ]
            );
        });
    }

    public function isVerified(int $userId): bool
    {
        $now = $this->simClock->now();

        $verification = RwaInvestorVerification::where('user_id', $userId)
            ->where('status', 'VERIFIED')
            ->where('expires_at', '>', $now)
            ->first();

        if ($verification !== null) {
            return true;
        }

        if ($this->kycLookup !== null) {
            $status = $this->kycLookup->getKycVerificationStatus($userId);
            if (! empty($status['is_verified'])) {
                return true;
            }
        }

        return false;
    }
}

<?php

declare(strict_types=1);

namespace Modules\Edu\Application\Actions;

use Illuminate\Support\Str;
use InvalidArgumentException;
use Modules\Edu\Domain\Exceptions\IdempotencyConflictException;
use Modules\Edu\Domain\Models\EduCorporateQuota;
use Modules\Edu\Domain\Models\EduProgram;
use Modules\Shared\Application\BaseAction;

final class AllocateCorporateQuotaAction extends BaseAction
{
    /**
     * Allocates B2B prepaid corporate training quota.
     *
     * @param  array{
     *     corporate_client_id: string,
     *     program_id: string,
     *     quota_total: int,
     *     unit_price_minor: int,
     *     idempotency_key?: string|null
     * }  $params
     */
    public function execute(array $params): EduCorporateQuota
    {
        $clientId = (string) ($params['corporate_client_id'] ?? '');
        $programId = (string) ($params['program_id'] ?? '');
        $quotaTotal = (int) ($params['quota_total'] ?? 0);
        $unitPriceMinor = (int) ($params['unit_price_minor'] ?? 0);
        $idempotencyKey = isset($params['idempotency_key']) ? (string) $params['idempotency_key'] : null;

        if ($clientId === '') {
            throw new InvalidArgumentException('corporate_client_id is required');
        }

        if ($quotaTotal <= 0) {
            throw new InvalidArgumentException('quota_total must be greater than zero');
        }

        if ($unitPriceMinor < 0) {
            throw new InvalidArgumentException('unit_price_minor cannot be negative');
        }

        EduProgram::findOrFail($programId);

        if ($idempotencyKey !== null) {
            $existing = EduCorporateQuota::where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                if ($existing->corporate_client_id === $clientId
                    && $existing->program_id === $programId
                    && $existing->quota_total === $quotaTotal
                    && $existing->unit_price_minor === $unitPriceMinor) {
                    return $existing;
                }
                throw new IdempotencyConflictException("Conflicting quota allocation payload for idempotency key: {$idempotencyKey}");
            }
        }

        return $this->transaction(function () use ($params, $clientId, $programId, $quotaTotal, $unitPriceMinor, $idempotencyKey) {
            return EduCorporateQuota::create([
                'id' => (string) ($params['id'] ?? Str::uuid()),
                'corporate_client_id' => $clientId,
                'program_id' => $programId,
                'quota_total' => $quotaTotal,
                'quota_used' => 0,
                'unit_price_minor' => $unitPriceMinor,
                'status' => 'ACTIVE',
                'idempotency_key' => $idempotencyKey,
            ]);
        });
    }
}

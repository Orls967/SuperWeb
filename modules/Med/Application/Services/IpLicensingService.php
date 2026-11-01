<?php

declare(strict_types=1);

namespace Modules\Med\Application\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Banking\Support\Posting;
use Modules\Med\Domain\Exceptions\LicenseOverlapException;
use Modules\Med\Domain\Models\MedIpAsset;
use Modules\Med\Domain\Models\MedIpLicense;
use Modules\Shared\Domain\Numerics\IntervalIndex;
use Modules\Shared\Domain\Numerics\IntervalIndexVerifier;
use RuntimeException;

class IpLicensingService
{
    public function __construct(
        protected ?LedgerService $ledgerService = null
    ) {
        $this->ledgerService = $ledgerService ?? app(LedgerService::class);
    }

    /**
     * Grant a content license to territory with mathematical IntervalIndex overlap verification.
     */
    public function grantLicense(array $params): MedIpLicense
    {
        if (empty($params['ip_id'])) {
            throw new InvalidArgumentException('IP ID is required.');
        }

        if (empty($params['licensee_entity_id'])) {
            throw new InvalidArgumentException('Licensee entity ID is required.');
        }

        $idempKey = $params['idempotency_key'] ?? null;
        if ($idempKey) {
            $existing = MedIpLicense::where('idempotency_key', $idempKey)->first();
            if ($existing) {
                return $existing;
            }
        }

        $ip = MedIpAsset::findOrFail($params['ip_id']);
        if ($ip->status !== 'ACTIVE') {
            throw new RuntimeException("IP Asset [{$ip->ip_code}] is not active.");
        }

        $startDate = CarbonImmutable::parse($params['start_date']);
        $endDate = CarbonImmutable::parse($params['end_date']);

        if ($endDate->lessThanOrEqualTo($startDate)) {
            throw new InvalidArgumentException("End date [{$endDate}] must be after start date [{$startDate}].");
        }

        $territory = strtoupper(trim((string) ($params['territory'] ?? 'GLOBAL')));
        $rightsType = strtoupper(trim((string) ($params['rights_type'] ?? 'STREAMING')));
        $isExclusive = (bool) ($params['is_exclusive'] ?? false);

        // Fetch all existing active licenses for this IP to detect overlaps via IntervalIndex (B0″.6)
        $existingLicenses = MedIpLicense::where('ip_id', $ip->id)
            ->where('status', 'ACTIVE')
            ->get();

        $intervals = [];
        foreach ($existingLicenses as $lic) {
            $intervals[] = [
                'id' => (string) $lic->id,
                'territory' => (string) $lic->territory,
                'right_type' => (string) $lic->rights_type,
                'start' => CarbonImmutable::parse($lic->start_date)->toDateString(),
                'end' => CarbonImmutable::parse($lic->end_date)->toDateString(),
                'exclusive' => (bool) $lic->is_exclusive,
            ];
        }

        // Add candidate license to interval set
        $candidateId = 'candidate-'.Str::random(6);
        $intervals[] = [
            'id' => $candidateId,
            'territory' => $territory,
            'right_type' => $rightsType,
            'start' => $startDate->toDateString(),
            'end' => $endDate->toDateString(),
            'exclusive' => $isExclusive,
        ];

        // Detect conflicts using IntervalIndex (O(N log N))
        $result = IntervalIndex::detectConflicts($intervals);

        // Verify certificate via IntervalIndexVerifier (PR2)
        IntervalIndexVerifier::verify($result, $intervals);

        if ($result['has_conflict']) {
            $details = json_encode($result['conflicts']);
            throw new LicenseOverlapException("License interval conflict detected for IP [{$ip->ip_code}] in territory [{$territory}]: {$details}");
        }

        $minGuarantee = (int) ($params['minimum_guarantee_minor'] ?? 0);
        $advancePayment = (int) ($params['advance_payment_minor'] ?? 0);
        $royaltyRate = (float) ($params['royalty_rate_pct'] ?? 15.0);
        $channel = (string) ($params['channel'] ?? 'STREAMING');

        $licenseCode = 'LIC-'.strtoupper(substr(hash('sha256', "{$ip->id}:{$params['licensee_entity_id']}:{$territory}:{$startDate->toDateString()}"), 0, 10));

        return DB::transaction(function () use ($ip, $params, $licenseCode, $territory, $rightsType, $channel, $startDate, $endDate, $isExclusive, $minGuarantee, $advancePayment, $royaltyRate, $idempKey) {
            $license = MedIpLicense::create([
                'id' => (string) Str::uuid(),
                'license_code' => $licenseCode,
                'contract_number' => $params['contract_number'] ?? null,
                'ip_id' => $ip->id,
                'licensee_entity_id' => $params['licensee_entity_id'],
                'rights_type' => $rightsType,
                'channel' => $channel,
                'territory' => $territory,
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
                'is_exclusive' => $isExclusive,
                'royalty_rate_pct' => $royaltyRate,
                'minimum_guarantee_minor' => $minGuarantee,
                'advance_payment_minor' => $advancePayment,
                'idempotency_key' => $idempKey,
                'status' => 'ACTIVE',
            ]);

            if ($minGuarantee > 0) {
                $idemp = hash('sha256', "IP-LIC-MG:{$licenseCode}:{$minGuarantee}");
                $this->ledgerService->post(new PostingDTO(
                    type: 'MEDIA_IP_LICENSING_FEE',
                    description: "Minimum guarantee for IP License {$licenseCode}",
                    idempotencyKey: $idemp,
                    entries: Posting::lines()
                        ->debit('med:licensing_receivable:IDR', $minGuarantee)
                        ->credit('med:ip_licensing_revenue:IDR', $minGuarantee)
                        ->toEntries('IDR'),
                    referenceType: 'MED_IP_LICENSE',
                    referenceId: $licenseCode,
                ));
            }

            return $license;
        });
    }

    /**
     * Generate and independently verify interval coverage certificate for an IP asset.
     */
    public function generateIntervalCertificate(string $ipId): array
    {
        $licenses = MedIpLicense::where('ip_id', $ipId)
            ->where('status', 'ACTIVE')
            ->get();

        $intervals = [];
        foreach ($licenses as $lic) {
            $intervals[] = [
                'id' => (string) $lic->id,
                'territory' => (string) $lic->territory,
                'right_type' => (string) $lic->rights_type,
                'start' => CarbonImmutable::parse($lic->start_date)->toDateString(),
                'end' => CarbonImmutable::parse($lic->end_date)->toDateString(),
                'exclusive' => (bool) $lic->is_exclusive,
            ];
        }

        $result = IntervalIndex::detectConflicts($intervals);
        IntervalIndexVerifier::verify($result, $intervals);

        return [
            'ip_id' => $ipId,
            'total_licenses' => count($intervals),
            'intervals' => $intervals,
            'has_conflict' => $result['has_conflict'],
            'conflicts' => $result['conflicts'],
            'certificate' => $result['certificate'],
            'is_valid' => true,
        ];
    }
}

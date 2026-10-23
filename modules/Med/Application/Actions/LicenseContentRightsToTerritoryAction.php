<?php

declare(strict_types=1);

namespace Modules\Med\Application\Actions;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Banking\Support\Posting;
use Modules\Med\Domain\Models\MedIpAsset;
use Modules\Med\Domain\Models\MedIpLicense;
use Modules\Shared\Application\BaseAction;
use RuntimeException;

class LicenseContentRightsToTerritoryAction extends BaseAction
{
    public function __construct(
        protected LedgerService $ledgerService
    ) {}

    /**
     * @param  array<string, mixed>  $params
     */
    public function execute(array $params): MedIpLicense
    {
        $ipAsset = MedIpAsset::findOrFail($params['ip_id']);

        if ($ipAsset->status !== 'ACTIVE') {
            throw new RuntimeException("IP Asset [{$ipAsset->ip_code}] is not active.");
        }

        return DB::transaction(function () use ($params, $ipAsset) {
            $licensee = (string) $params['licensee_entity_id'];
            $channel = (string) ($params['channel'] ?? 'STREAMING');
            $territory = (string) ($params['territory'] ?? 'GLOBAL');
            $minGuarantee = (int) ($params['minimum_guarantee_minor'] ?? 0);
            $royaltyRate = (float) ($params['royalty_rate_pct'] ?? 15.0);
            $startDate = $params['start_date'] ?? Carbon::now()->toDateString();
            $endDate = $params['end_date'] ?? Carbon::now()->addYear()->toDateString();

            $licenseCode = 'LIC-'.strtoupper(substr(hash('sha256', "{$ipAsset->id}:{$licensee}:{$territory}"), 0, 10));

            $license = MedIpLicense::create([
                'id' => (string) Str::uuid(),
                'license_code' => $licenseCode,
                'ip_id' => $ipAsset->id,
                'licensee_entity_id' => $licensee,
                'channel' => $channel,
                'territory' => $territory,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'royalty_rate_pct' => $royaltyRate,
                'minimum_guarantee_minor' => $minGuarantee,
                'status' => 'ACTIVE',
            ]);

            if ($minGuarantee > 0) {
                $idempotencyKey = hash('sha256', "IP-LIC:{$licenseCode}:{$minGuarantee}");
                $this->ledgerService->post(new PostingDTO(
                    type: 'MEDIA_IP_LICENSING_FEE',
                    description: "Minimum guarantee for IP License {$licenseCode}",
                    idempotencyKey: $idempotencyKey,
                    entries: Posting::lines()
                        ->debit('med:licensing_receivable:IDR', $minGuarantee)
                        ->credit('med:ip_licensing_revenue:IDR', $minGuarantee)
                        ->toEntries('IDR'),
                    referenceType: 'MED_IP_LICENSE',
                    referenceId: $licenseCode,
                ));
            }

            $this->audit(
                action: 'med_ip_license_granted',
                auditable: $license,
                newValues: ['license_code' => $licenseCode, 'territory' => $territory],
                impactType: 'ip_rights',
            );

            return $license;
        });
    }
}

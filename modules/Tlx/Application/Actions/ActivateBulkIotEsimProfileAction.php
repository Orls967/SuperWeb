<?php

declare(strict_types=1);

namespace Modules\Tlx\Application\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Shared\Application\BaseAction;
use Modules\Tlx\Domain\Models\SimSubscription;

class ActivateBulkIotEsimProfileAction extends BaseAction
{
    /**
     * @param  array<string, mixed>  $params
     * @return list<SimSubscription>
     */
    public function execute(array $params): array
    {
        return DB::transaction(function () use ($params) {
            $count = max(1, (int) ($params['count'] ?? 1));
            $planCode = (string) ($params['plan_code'] ?? 'IOT_M2M_STANDARD');
            $quotaGb = (float) ($params['quota_allowance_gb'] ?? 10.0);
            $walletId = (string) ($params['wallet_id'] ?? 'WLT-ENTERPRISE-01');

            $activated = [];

            for ($i = 0; $i < $count; $i++) {
                $hashSuffix = substr(hash('sha256', "{$walletId}:{$planCode}:{$i}:".microtime()), 0, 8);
                $msisdn = '62899'.str_pad((string) rand(1000000, 9999999), 7, '0', STR_PAD_LEFT);
                $iccid = '896200'.strtoupper($hashSuffix);

                $sim = SimSubscription::create([
                    'id' => (string) Str::uuid(),
                    'msisdn' => $msisdn,
                    'iccid' => $iccid,
                    'account_tier' => 'PARENT',
                    'wallet_id' => $walletId,
                    'plan_code' => $planCode,
                    'quota_allowance_gb' => $quotaGb,
                    'quota_remaining_gb' => $quotaGb,
                    'rollover_quota_gb' => 0.0,
                    'auto_renew_fee_minor' => 50000,
                    'status' => 'ACTIVE',
                ]);

                $activated[] = $sim;
            }

            $this->audit(
                action: 'tlx_esim_bulk_activated',
                newValues: ['count' => count($activated), 'plan' => $planCode],
                impactType: 'esim_provisioning',
            );

            return $activated;
        });
    }
}

<?php

declare(strict_types=1);

namespace Modules\Core\Console\Commands;

use Illuminate\Console\Command;
use Modules\Core\Application\Api\ApiClientLifecycleService;
use Modules\Core\Domain\Models\CoreApiClient;
use Modules\Core\Domain\Models\CoreApiClientKey;

class ApiKeysRotateCommand extends Command
{
    protected $signature = 'api:keys-rotate {--client= : Specific client code to rotate} {--grace-days=7 : Grace period days for previous key} {--expire-old : Automatically revoke keys past expiration date}';

    protected $description = 'Rotate API client keys maintaining ≤ 2 active keys during grace period and revoking expired keys (W8-D48).';

    public function handle(ApiClientLifecycleService $service): int
    {
        $this->info('Starting API Client Key Rotation...');

        // 1. Expire old keys
        $expiredCount = CoreApiClientKey::where('status', 'active')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->update([
                'status' => 'revoked',
                'revoked_at' => now(),
            ]);

        $this->line("Revoked {$expiredCount} expired keys past grace period.");

        // 2. Rotate specified or selected clients
        $clientCode = $this->option('client');
        $graceDays = (int) $this->option('grace-days');

        $query = CoreApiClient::where('is_active', true);
        if ($clientCode) {
            $query->where('code', $clientCode);
        }

        $clients = $query->get();
        $rotatedCount = 0;

        foreach ($clients as $client) {
            $res = $service->rotateKey($client, null, $graceDays);
            $this->line("Rotated key for client [{$client->client_code}]: New Key Prefix [{$res['key']->key_prefix}]");
            $rotatedCount++;
        }

        $this->info("Completed key rotation for {$rotatedCount} clients.");

        return self::SUCCESS;
    }
}


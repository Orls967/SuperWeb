<?php

declare(strict_types=1);

namespace Modules\Core\Application\Api;

use Modules\Core\Domain\Algorithms\Api\WebhookSignatureVerifier;
use Modules\Core\Domain\Models\CoreApiClient;
use Modules\Core\Domain\Models\CoreEdiInterchange;
use Modules\Core\Domain\Models\CoreWebhookDispatch;

class CoreApiAuditor
{
    public function __construct(
        private readonly WebhookSignatureVerifier $signatureVerifier,
        private readonly WebhookSubscriptionService $subscriptionService,
    ) {}

    /**
     * @return array{discrepancies: int, invalid_records: list<string>, stats: array<string, int>}
     */
    public function audit(): array
    {
        $invalidRecords = [];
        $discrepancies = 0;

        // 1. Audit Core Webhook Dispatches
        $dispatches = CoreWebhookDispatch::with('webhook')
            ->where('status', 'delivered')
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        foreach ($dispatches as $dispatch) {
            if ($dispatch->webhook) {
                $secrets = [];
                try {
                    $secrets[] = $this->subscriptionService->getActiveSecret($dispatch->webhook);
                } catch (\Throwable) {
                }
                try {
                    $prev = $this->subscriptionService->getPreviousSecret($dispatch->webhook);
                    if ($prev) {
                        $secrets[] = $prev;
                    }
                } catch (\Throwable) {
                }

                $result = $this->signatureVerifier->verify(
                    $dispatch->signature_header,
                    $dispatch->payload_json,
                    $secrets,
                    $dispatch->timestamp_unix,
                    86400 * 365 // historical tolerance
                );

                $status = is_array($result) ? ($result['status'] ?? 'UNKNOWN') : ($result->status ?? 'UNKNOWN');
                if ($status !== WebhookSignatureVerifier::STATUS_OK) {
                    $discrepancies++;
                    $invalidRecords[] = "Corrupted CoreWebhookDispatch ID {$dispatch->id}: {$status}";
                }
            }
        }

        // 2. Audit Core EDI Interchanges
        $postedInterchanges = CoreEdiInterchange::where('status', 'posted')
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        foreach ($postedInterchanges as $edi) {
            if (empty($edi->contrl_payload) || empty($edi->handler_module) || empty($edi->target_reference)) {
                $discrepancies++;
                $invalidRecords[] = "Corrupted CoreEdiInterchange ID {$edi->id}: Missing CONTRL or handler target reference";
            }
        }

        // 3. Audit API Clients
        $activeClients = CoreApiClient::where('is_active', true)
            ->with('keys')
            ->get();

        foreach ($activeClients as $client) {
            $hasActiveKey = $client->keys->where('status', 'active')->isNotEmpty();
            if (!$hasActiveKey) {
                $discrepancies++;
                $invalidRecords[] = "Corrupted CoreApiClient ID {$client->id}: Active client has no active keys";
            }
        }

        return [
            'discrepancies' => $discrepancies,
            'invalid_records' => $invalidRecords,
            'stats' => [
                'dispatches' => $dispatches->count(),
                'edi_posted' => $postedInterchanges->count(),
                'active_clients' => $activeClients->count(),
            ],
        ];
    }
}

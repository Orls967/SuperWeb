<?php

declare(strict_types=1);

namespace Modules\Core\Http\Controllers\Api\V3;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Core\Application\Services\TwelveLinesWebhookDispatcherService;
use Modules\Core\Domain\Models\PartnerWebhook;
use Modules\Core\Domain\Models\WebhookDelivery;

class PartnerWebhookController extends Controller
{
    public function __construct(
        private readonly TwelveLinesWebhookDispatcherService $dispatcher,
    ) {}

    /**
     * List registered partner webhooks
     */
    public function index(Request $request): JsonResponse
    {
        $tenantId = (string) ($request->header('X-Tenant-ID') ?: $request->query('tenant_id', 'tenant-default'));

        $webhooks = PartnerWebhook::where('tenant_id', $tenantId)->get();

        return response()->json([
            'status' => 'success',
            'data' => $webhooks,
        ]);
    }

    /**
     * Register a new partner webhook endpoint
     */
    public function store(Request $request): JsonResponse
    {
        $tenantId = (string) ($request->header('X-Tenant-ID') ?: $request->input('tenant_id', 'tenant-default'));
        $partnerName = (string) $request->input('partner_name', 'Partner Integration');
        $endpointUrl = (string) $request->input('endpoint_url', 'https://partner.example.com/webhook');
        $signingKey = (string) ($request->input('hmac_secret') ?: \Illuminate\Support\Str::random(32));

        $webhook = PartnerWebhook::create([
            'tenant_id' => $tenantId,
            'partner_name' => $partnerName,
            'endpoint_url' => $endpointUrl,
            'hmac_secret' => $signingKey,
            'retry_limit' => (int) $request->input('retry_limit', 5),
            'timeout_seconds' => (int) $request->input('timeout_seconds', 30),
            'is_active' => true,
        ]);

        return response()->json([
            'status' => 'success',
            'data' => $webhook,
        ], 201);
    }

    /**
     * Replay a failed or DLQ message
     */
    public function replay(Request $request, string $id): JsonResponse
    {
        /** @var WebhookDelivery|null $delivery */
        $delivery = WebhookDelivery::find($id);

        if (! $delivery) {
            return response()->json([
                'status' => 'error',
                'message' => 'Webhook delivery record not found.',
            ], 404);
        }

        $success = $this->dispatcher->replayDeadLetterMessage($delivery);

        return response()->json([
            'status' => $success ? 'success' : 'failed',
            'data' => $delivery->fresh(),
        ]);
    }
}

<?php

declare(strict_types=1);

namespace Modules\Core\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\Application\Api\WebhookSubscriptionService;
use Modules\Core\Application\Tenancy\TenantContext;
use Modules\Core\Domain\Models\CoreWebhook;

class ApiV2WebhookController extends Controller
{
    public function __construct(
        private readonly WebhookSubscriptionService $webhookService,
    ) {}

    public function index(): JsonResponse
    {
        $context = TenantContext::current() ?? new TenantContext('tenant-default');
        $webhooks = CoreWebhook::where('tenant_id', $context->tenantId)->get();

        return response()->json([
            'data' => $webhooks->map(fn ($wh) => [
                'id' => $wh->id,
                'code' => $wh->code,
                'name' => $wh->name,
                'target_url' => $wh->target_url,
                'event_topics' => $wh->event_topics,
                'circuit_state' => $wh->circuit_state,
                'is_active' => (bool) $wh->is_active,
            ]),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $context = TenantContext::current() ?? new TenantContext('tenant-default');

        $result = $this->webhookService->register(
            tenantId: $context->tenantId,
            code: (string) $request->input('code', 'WH-' . uniqid()),
            name: (string) $request->input('name', 'Webhook Subscription'),
            targetUrl: (string) $request->input('target_url'),
            eventTopics: (array) $request->input('event_topics', ['*'])
        );

        return response()->json([
            'data' => [
                'id' => $result['webhook']->id,
                'code' => $result['webhook']->code,
                'name' => $result['webhook']->name,
                'target_url' => $result['webhook']->target_url,
                'secret' => $result['secret'],
            ],
        ], 201);
    }

    public function rotateSecret(int $id): JsonResponse
    {
        $context = TenantContext::current() ?? new TenantContext('tenant-default');
        /** @var CoreWebhook $webhook */
        $webhook = CoreWebhook::where('tenant_id', $context->tenantId)->findOrFail($id);

        $result = $this->webhookService->rotateSecret($webhook);

        return response()->json([
            'data' => [
                'id' => $result['webhook']->id,
                'new_secret' => $result['new_secret'],
                'grace_period_expires_at' => $result['webhook']->previous_secret_expires_at?->toIso8601String(),
            ],
        ]);
    }
}


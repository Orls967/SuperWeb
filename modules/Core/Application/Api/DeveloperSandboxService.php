<?php

declare(strict_types=1);

namespace Modules\Core\Application\Api;

class DeveloperSandboxService
{
    /**
     * @return array<string, mixed>
     */
    public function getSandboxMetadata(): array
    {
        return [
            'api_version' => 'v2',
            'base_url' => config('app.url') . '/api/v2',
            'auth_header' => 'Authorization: Bearer <api_token>',
            'idempotency_header' => 'Idempotency-Key: <uuid-or-string-16-128-chars>',
            'supported_resources' => [
                'purchase-orders' => '/api/v2/purchase-orders',
                'warehouses' => '/api/v2/warehouses',
                'shipments' => '/api/v2/shipments',
                'wholesale-catalogs' => '/api/v2/wholesale-catalogs',
                'exchange-rates' => '/api/v2/exchange-rates',
                'loans' => '/api/v2/loans',
                'distribution-orders' => '/api/v2/distribution-orders',
                'import-orders' => '/api/v2/import-orders',
            ],
            'rate_limit' => [
                'standard' => '1,000 req/min',
                'burst' => '2,000 req burst',
                'algorithm' => 'Integer Token Bucket (B1)',
            ],
            'webhook_specs' => [
                'signature_header' => 'X-Signature: t=<unix_ts>,v1=<hmac_sha256>',
                'replay_window' => '300 seconds (5 minutes)',
                'dual_secret_support' => true,
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function getCurlExamples(string $baseUrl = 'https://api.example.com'): array
    {
        return [
            'list_resources' => "curl -X GET '{$baseUrl}/api/v2/purchase-orders?limit=10' \\\n  -H 'Authorization: Bearer sw_live_xxx' \\\n  -H 'Accept: application/json'",
            'create_idempotent' => "curl -X POST '{$baseUrl}/api/v2/purchase-orders' \\\n  -H 'Authorization: Bearer sw_live_xxx' \\\n  -H 'Idempotency-Key: 1111-2222-3333-4444' \\\n  -H 'Content-Type: application/json' \\\n  -d '{\"po_number\": \"PO-2026-001\", \"supplier_id\": 10, \"total_cents\": 5000000}'",
            'ingest_edi' => "curl -X POST '{$baseUrl}/api/v2/edi/interchange' \\\n  -H 'Authorization: Bearer sw_live_xxx' \\\n  -H 'Idempotency-Key: edi-ingest-001' \\\n  -H 'Content-Type: text/plain' \\\n  --data-binary @interchange.edi",
        ];
    }
}


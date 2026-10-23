<?php

declare(strict_types=1);

namespace App\Quality\OpenApi;

use App\Quality\Modules\ModuleRegistry;
use App\Quality\Routing\RouteAuthorizationScanner;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use Illuminate\Support\Str;
use Symfony\Component\Yaml\Yaml;

final class OpenApiSpecGenerator
{
    /**
     * Canonical 46 enterprise modules in the AutoServe ecosystem.
     *
     * @var list<string>
     */
    public const CANONICAL_46_MODULES = [
        'Agency',
        'Agri',
        'Asset',
        'AutoDex',
        'AutoServe',
        'B2b',
        'Banking',
        'CloudKitchen',
        'Contract',
        'ControlTower',
        'Crypto',
        'Distribution',
        'Edu',
        'Egy',
        'EnterpriseFinance',
        'Epc',
        'Esg',
        'Ev',
        'Finance',
        'Fleet',
        'Hcm',
        'Hospital',
        'Hotel',
        'Insurance',
        'Intercompany',
        'International',
        'Inventory',
        'Logistics',
        'Mall',
        'Manufacturing',
        'Med',
        'Mining',
        'Partner',
        'Party',
        'Payment',
        'Plm',
        'Pricing',
        'Procurement',
        'Proptech',
        'Resto',
        'Ret',
        'Rwa',
        'Store',
        'Supplier',
        'Telematics',
        'Tlx',
    ];

    /**
     * Complete registry of all modules in the codebase (kernel + business).
     *
     * @var list<string>
     */
    public const ALL_MODULES = [
        'Agency',
        'Agri',
        'Asset',
        'AutoDex',
        'AutoServe',
        'B2b',
        'Banking',
        'CloudKitchen',
        'Contract',
        'ControlTower',
        'Core',
        'Crypto',
        'Distribution',
        'Edu',
        'Egy',
        'EnterpriseFinance',
        'Epc',
        'Esg',
        'Ev',
        'Finance',
        'Fleet',
        'Hcm',
        'Hospital',
        'Hotel',
        'Insurance',
        'Integration',
        'Intercompany',
        'International',
        'Inventory',
        'Logistics',
        'Mall',
        'Manufacturing',
        'Med',
        'Mining',
        'Partner',
        'Party',
        'Payment',
        'Plm',
        'Pricing',
        'Procurement',
        'Proptech',
        'Resto',
        'Ret',
        'Rwa',
        'Shared',
        'Store',
        'Supplier',
        'Telematics',
        'Tlx',
        'Trade',
        'TradeFinance',
        'Treasury',
        'Vending',
        'Venue',
        'Wealth',
        'Wms',
    ];

    /**
     * @var array<string, string>
     */
    private const MODULE_DESCRIPTIONS = [
        'Agency' => 'Agency ecosystem, leads, tiering, agent commissions, and regulatory compliance',
        'Agri' => 'Agribusiness supply chain, farm contracts, commodity tracking, and harvest management',
        'Asset' => 'Fixed asset lifecycle, RFID tagging, depreciation schedules, and maintenance audits',
        'AutoDex' => 'Automotive marketplace catalog, car specifications, wishlists, and inspection telemetry',
        'AutoServe' => 'Automotive service workshop operations, bay scheduling, mechanics, and parts estimates',
        'B2b' => 'Business-to-business commerce, purchase agreements, bulk orders, and enterprise accounts',
        'Banking' => 'Core banking ledger, double-entry accounts, transfer limits, and wallet reconciliation',
        'CloudKitchen' => 'Ghost kitchen operations, shared kitchen stations, catering subscriptions, and dispatching',
        'Contract' => 'Digital contracts, cryptographic hash-chain clause versioning, and legal counter-signing',
        'ControlTower' => 'Conglomerate logistics control tower, SLA monitoring, and cross-carrier orchestration',
        'Core' => 'Platform kernel, multi-tenant RBAC, unified audit logs, mobile vector clocks, and golden run orchestrator',
        'Crypto' => 'Cryptocurrency asset management, real-time market price ticks, and threshold alerts',
        'Distribution' => 'Wholesale distribution networks, sales territories, distributor quotas, and rebate management',
        'Edu' => 'Higher education grants, academic program lifecycles, and institutional accreditation',
        'Egy' => 'Energy management, power purchase agreements (PPA), and renewable smart-grid tracking',
        'EnterpriseFinance' => 'Corporate financial planning, multi-entity budget envelopes, and CAPEX/OPEX approvals',
        'Epc' => 'Engineering, procurement, and construction milestones, contractor billing, and project progress',
        'Esg' => 'Environmental, Social, and Governance telemetry, GHG carbon accounting, and sustainability audits',
        'Ev' => 'Electric vehicle charging infrastructure, OCPP session billing, and station reservations',
        'Finance' => 'Multi-finance installments, loan amortizations, credit risk scoring, and collection schedules',
        'Fleet' => 'Fleet management, vehicle leasing (PSAK 73 / IFRS 16), vehicle passports, and fuel dispatch',
        'Hcm' => 'Human capital management, PPh 21 TER payroll computation, BPJS deductions, and gig bounties',
        'Hospital' => 'Hospital clinical trials, ESI triage, pediatric dosing calculations, and INA-CBG claim audits',
        'Hotel' => 'Hospitality property reservations, room inventory, folio charges, and guest checkout settlement',
        'Insurance' => 'Insurtech policy issuance, claim adjudication, reinsurance treaties, and premium collections',
        'Integration' => 'Enterprise integration spine, dead-letter queues (DLQ), and external partner webhook adapters',
        'Intercompany' => 'Intercompany transfer pricing, cross-entity settlement, and consolidation balancing',
        'International' => 'Cross-border joint ventures, international trade corridors, and FX hedging',
        'Inventory' => 'Enterprise inventory explorer, SKU lot tracking, valuation layers, and stock balances',
        'Logistics' => 'Multimodal logistics freight forwarding, container dwell audits, customs declarations, and tracking events',
        'Mall' => 'Commercial retail mall leasing, tenant billing, footfall heatmaps, and loyalty programs',
        'Manufacturing' => 'Manufacturing BOM trees, production shop floor routing, MRP planning, and OEE costing',
        'Med' => 'Media broadcasting rights, box office theatrical royalties, and content syndication contracts',
        'Mining' => 'Mining pit dispatch, blast monitoring, smelter mass-balance recovery, and PNBP royalty calculations',
        'Partner' => 'Partner onboarding, KYC due diligence, revenue sharing, and affiliate network tracking',
        'Party' => 'Universal party master (parties, individuals, legal entities), KYC/AML verification, and deduplication',
        'Payment' => 'Payment transaction processing, virtual accounts, and payment gateway inspection',
        'Plm' => 'Product lifecycle management, engineering change orders (ECO), and component revisions',
        'Pricing' => 'Unified dynamic pricing engines, rate cards, and contract priority price evaluation',
        'Procurement' => 'Procurement requisitions (PR -> RFQ -> Tender -> PO), 3-way matching, and AP invoicing',
        'Proptech' => 'Real estate asset tokenization, smart building IoT telemetry, and lease amortizations',
        'Resto' => 'Restaurant food & beverage operations, table management, POS ordering, and kitchen display systems',
        'Ret' => 'Omnichannel retail operations, barcode scanning, loyalty redemptions, and store settlements',
        'Rwa' => 'Real-World Asset tokenization, fractional yield distribution, and custodian compliance',
        'Shared' => 'Shared kernel utilities, global search index, and enterprise navigation services',
        'Store' => 'E-commerce storefront catalog, customer shopping carts, checkout, and order fulfillment',
        'Supplier' => 'Supplier risk monitoring, compliance audits, quality ratings, and vendor tiering',
        'Telematics' => 'IoT telematics device ingestion, GPS breadcrumbs, vehicle diagnostics, and alert thresholds',
        'Tlx' => 'Telecommunications infrastructure, 5G network slice SLA enforcement, and fiber optic colocation',
        'Trade' => 'Commodity trade exchange, blind bidding tenders, and exchange clearing',
        'TradeFinance' => 'Trade finance letters of credit (LC), cross-border escrows, and trade guarantees',
        'Treasury' => 'Treasury cash positioning, multi-currency liquidity pools, and daily FX revaluation',
        'Vending' => 'Automated smart vending units, telemetry dispense actions, and cashbox reconciliations',
        'Venue' => 'Entertainment venue ticketing, RFID turnstile scans, artist contracts, and seat reservations',
        'Wealth' => 'Private wealth advisory, discretionary portfolio rebalancing, and investor risk profiling',
        'Wms' => 'Warehouse management system (WMS), slotting algorithms, wave picking, and packing lists',
    ];

    /**
     * @param  array<string, mixed>|null  $routeRolesMap
     */
    public function __construct(
        private readonly ?ModuleRegistry $moduleRegistry = null,
        private readonly ?array $routeRolesMap = null,
    ) {}

    /**
     * Generate the complete OpenAPI 3.1.0 specification structure.
     *
     * @return array<string, mixed>
     */
    public function buildSpec(): array
    {
        $roleMap = $this->routeRolesMap ?? $this->loadRouteRolesMap();
        $routes = RouteFacade::getRoutes()->getRoutes();

        return [
            'openapi' => '3.1.0',
            'info' => $this->buildInfoSection(),
            'servers' => $this->buildServersSection(),
            'tags' => $this->buildTagsSection(),
            'paths' => $this->buildPathsSection($routes, $roleMap),
            'components' => $this->buildComponentsSection(),
        ];
    }

    /**
     * Build the OpenAPI Info section detailing architecture invariants.
     *
     * @return array<string, mixed>
     */
    private function buildInfoSection(): array
    {
        $description = <<<'MARKDOWN'
# AutoServe Enterprise API & Microservices Specification (OpenAPI 3.1.0)

Dokumentasi spesifikasi resmi antarmuka pemrograman aplikasi (API) dan gateway layanan konglomerasi multi-lini **AutoServe Enterprise Core Platform** yang mencakup seluruh 46 modul bisnis dan modul kernel arsitektur.

---

## 1. Arsitektur Presisi Finansial: Integer Minor-Unit (`int64`)
Seluruh nilai moneter dan mata uang dalam spesifikasi ini diatur secara ketat oleh skema `MoneyMinorUnit`:
- **Tipe Data**: `type: integer`, `format: int64`.
- **Unit Dasar**: Disimpan dan dikirim dalam unit terkecil mata uang (*minor unit*, misalnya `1 IDR = 1 unit` senilai Rp 1; `1 USD = 100 units` senilai 1 sen).
- **Invarian Mutlak**: Nilai pecahan/desimal (`float` atau `number` IEEE 754) **DILARANG KERAS** pada seluruh parameter, body, dan respons guna mengeliminasi galat pembulatan (*rounding errors*) pada buku besar berimbang (*double-entry balancing*).

---

## 2. Jaminan Idempotensi Deterministik & Anti-Replay
Untuk mencegah pemesanan ganda, duplikasi transaksi debit/kredit, atau kesalahan transmisi jaringan:
- Seluruh endpoint mutasi (`POST`, `PUT`, `PATCH`) mendukung header `Idempotency-Key` (UUIDv4/v7).
- Server menggunakan kunci konkurensi atomik (`lockForUpdate`). Bila permintaan dengan kunci yang sama diulang:
  - Transaksi tidak diproses ulang dan saldo tidak didebit kembali.
  - Mengembalikan respons transaksi asli dengan status HTTP `200 OK` (atau `409 Conflict` jika payload berubah).
  - Menyertakan header `X-Idempotent-Replay: true`.

---

## 3. Verifikasi Dua-Sumber & Rantai Kustodi Kriptografis (Hash-Chain Proof)
Seluruh log transaksi, mutasi finansial, pergerakan barang, dan perubahan status kontrak disegel secara kriptografis:
- **Struktur**: Append-only SHA-256 hash-chain berurut (`hash = SHA256(prev_hash || payload_digest || sequence)`).
- **Skema Bukti**: Didefinisikan dalam `HashChainProof` untuk audit forensik independen secara berkala melalui perintah Artisan audit.

---

## 4. Matriks Otorisasi Berbasis Peran (RBAC 60 Persona)
Setiap operasi API dilengkapi metadata perizinan `x-allowed-roles` yang dipetakan secara statis ke dalam matriks peran sistem (60 peran terakreditasi).
MARKDOWN;

        return [
            'title' => 'AutoServe Enterprise Conglomerate API',
            'version' => '3.1.0',
            'description' => $description,
            'contact' => [
                'name' => 'AutoServe Architecture & Security Board',
                'email' => 'architecture@autoserve.internal',
                'url' => 'https://autoserve.local/docs/api',
            ],
            'license' => [
                'name' => 'Proprietary - AutoServe Group',
                'identifier' => 'Proprietary',
            ],
        ];
    }

    /**
     * @return list<array<string, string>>
     */
    private function buildServersSection(): array
    {
        return [
            [
                'url' => 'https://api.autoserve.local',
                'description' => 'Enterprise Production Gateway (mTLS & Sanctum PAT)',
            ],
            [
                'url' => 'http://127.0.0.1:8000',
                'description' => 'Local Development & Integration Testing Server',
            ],
        ];
    }

    /**
     * Build the tags section covering all modules.
     *
     * @return list<array<string, string>>
     */
    private function buildTagsSection(): array
    {
        $tags = [];
        $modules = self::ALL_MODULES;
        sort($modules);

        foreach ($modules as $module) {
            $desc = self::MODULE_DESCRIPTIONS[$module] ?? "Modul operasional domain {$module} dalam platform AutoServe";
            $tags[] = [
                'name' => $module,
                'description' => $desc,
            ];
        }

        return $tags;
    }

    /**
     * Build the components section with schemas, parameters, and security schemes.
     *
     * @return array<string, mixed>
     */
    private function buildComponentsSection(): array
    {
        return [
            'securitySchemes' => [
                'BearerAuth' => [
                    'type' => 'http',
                    'scheme' => 'bearer',
                    'bearerFormat' => 'Sanctum PAT',
                    'description' => 'Laravel Sanctum Personal Access Token authentication with granular abilities',
                ],
                'SessionCookieAuth' => [
                    'type' => 'apiKey',
                    'in' => 'cookie',
                    'name' => 'autoserve_session',
                    'description' => 'Encrypted session cookie authentication for web portal and SPA clients',
                ],
                'HmacWebhookSignature' => [
                    'type' => 'apiKey',
                    'in' => 'header',
                    'name' => 'X-Signature-HMAC-SHA256',
                    'description' => 'HMAC-SHA256 signature of request payload combined with X-Timestamp header for replay mitigation',
                ],
                'MtlsClientCert' => [
                    'type' => 'mutualTLS',
                    'description' => 'Mutual TLS (mTLS) X.509 client certificate authentication for inter-bank and high-assurance integrations',
                ],
            ],
            'parameters' => [
                'IdempotencyKeyHeader' => [
                    'name' => 'Idempotency-Key',
                    'in' => 'header',
                    'required' => false,
                    'description' => 'Deterministic UUID idempotency key to prevent accidental double-mutation or retry duplication',
                    'schema' => [
                        'type' => 'string',
                        'format' => 'uuid',
                        'example' => '9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d',
                    ],
                ],
                'CorrelationIdHeader' => [
                    'name' => 'X-Correlation-Id',
                    'in' => 'header',
                    'required' => false,
                    'description' => 'Distributed tracing correlation identifier across microservices and asynchronous workers',
                    'schema' => [
                        'type' => 'string',
                        'example' => 'corr-e89e2b0b-92e7-42d2-a15e-a03c5e6ab14d',
                    ],
                ],
                'CursorPaginationParam' => [
                    'name' => 'cursor',
                    'in' => 'query',
                    'required' => false,
                    'description' => 'Opaque keyset cursor for deterministic, high-performance pagination without offset drift',
                    'schema' => [
                        'type' => 'string',
                        'example' => 'eyJpZCI6MTUwLCJjcmVhdGVkX2F0IjoiMjAyNi0xMC0yMyJ9',
                    ],
                ],
            ],
            'schemas' => [
                'MoneyMinorUnit' => [
                    'type' => 'integer',
                    'format' => 'int64',
                    'description' => 'Monetary amount expressed strictly in minor units (e.g. 1 IDR = 1 unit; cents for USD). Never use floating point decimals.',
                    'example' => 1500000000,
                ],
                'HashChainProof' => [
                    'type' => 'object',
                    'description' => 'Cryptographic append-only hash chain block verification record',
                    'properties' => [
                        'id' => [
                            'type' => 'string',
                            'format' => 'uuid',
                        ],
                        'sequence' => [
                            'type' => 'integer',
                            'format' => 'int64',
                            'example' => 1042,
                        ],
                        'hash' => [
                            'type' => 'string',
                            'example' => '4f53cda18c2baa0c0354bb5f9a3ecbe5ed12ab4d8e11ba873c2f11161202b945',
                        ],
                        'prev_hash' => [
                            'type' => 'string',
                            'example' => 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855',
                        ],
                        'payload_digest' => [
                            'type' => 'string',
                            'example' => 'ca978112ca1bbdcafac231b39a23dc4da786eff8147c4e72b9807785afee48bb',
                        ],
                        'verified_at' => [
                            'type' => 'string',
                            'format' => 'date-time',
                        ],
                        'status' => [
                            'type' => 'string',
                            'enum' => ['VERIFIED', 'TAMPERED', 'PENDING'],
                            'example' => 'VERIFIED',
                        ],
                    ],
                    'required' => ['sequence', 'hash', 'prev_hash', 'payload_digest', 'status'],
                ],
                'ProblemDetailsRfc7807' => [
                    'type' => 'object',
                    'description' => 'RFC 7807 compliant standardized problem details error payload',
                    'properties' => [
                        'type' => [
                            'type' => 'string',
                            'format' => 'uri',
                            'example' => 'https://api.autoserve.local/errors/validation-failed',
                        ],
                        'title' => [
                            'type' => 'string',
                            'example' => 'Unprocessable Entity',
                        ],
                        'status' => [
                            'type' => 'integer',
                            'example' => 422,
                        ],
                        'detail' => [
                            'type' => 'string',
                            'example' => 'The given data failed business invariant validation.',
                        ],
                        'instance' => [
                            'type' => 'string',
                            'example' => '/api/v1/logistics/shipments',
                        ],
                        'invalid_params' => [
                            'type' => 'array',
                            'items' => [
                                'type' => 'object',
                                'properties' => [
                                    'name' => ['type' => 'string'],
                                    'reason' => ['type' => 'string'],
                                ],
                            ],
                        ],
                        'timestamp' => [
                            'type' => 'string',
                            'format' => 'date-time',
                        ],
                        'correlation_id' => [
                            'type' => 'string',
                        ],
                    ],
                    'required' => ['type', 'title', 'status', 'detail'],
                ],
                'PaginatedEnvelope' => [
                    'type' => 'object',
                    'description' => 'Standardized paginated list envelope',
                    'properties' => [
                        'data' => [
                            'type' => 'array',
                            'items' => ['type' => 'object'],
                        ],
                        'meta' => [
                            'type' => 'object',
                            'properties' => [
                                'cursor' => ['type' => 'string', 'nullable' => true],
                                'next_cursor' => ['type' => 'string', 'nullable' => true],
                                'per_page' => ['type' => 'integer', 'example' => 25],
                                'total' => ['type' => 'integer', 'nullable' => true],
                            ],
                        ],
                        'links' => [
                            'type' => 'object',
                            'properties' => [
                                'first' => ['type' => 'string', 'nullable' => true],
                                'last' => ['type' => 'string', 'nullable' => true],
                                'prev' => ['type' => 'string', 'nullable' => true],
                                'next' => ['type' => 'string', 'nullable' => true],
                            ],
                        ],
                    ],
                    'required' => ['data', 'meta'],
                ],
                'BankingWallet' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'string', 'format' => 'uuid'],
                        'user_id' => ['type' => 'integer'],
                        'balance' => ['$ref' => '#/components/schemas/MoneyMinorUnit'],
                        'currency' => ['type' => 'string', 'example' => 'IDR'],
                        'status' => ['type' => 'string', 'enum' => ['ACTIVE', 'FROZEN', 'CLOSED']],
                    ],
                ],
                'LogisticsShipment' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'string', 'format' => 'uuid'],
                        'tracking_number' => ['type' => 'string', 'example' => 'SRX-2026-JKT-0042'],
                        'status' => ['type' => 'string', 'example' => 'IN_TRANSIT'],
                        'origin' => ['type' => 'string'],
                        'destination' => ['type' => 'string'],
                        'rate_minor' => ['$ref' => '#/components/schemas/MoneyMinorUnit'],
                    ],
                ],
                'ContractClause' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'string', 'format' => 'uuid'],
                        'contract_id' => ['type' => 'string', 'format' => 'uuid'],
                        'title' => ['type' => 'string'],
                        'content' => ['type' => 'string'],
                        'hash_proof' => ['$ref' => '#/components/schemas/HashChainProof'],
                    ],
                ],
            ],
        ];
    }

    /**
     * Build all path entries by introspecting Laravel routes.
     *
     * @param  list<Route>  $routes
     * @param  array<string, mixed>  $roleMap
     * @return array<string, array<string, mixed>>
     */
    private function buildPathsSection(array $routes, array $roleMap): array
    {
        $paths = [];
        $seenOperationIds = [];

        foreach ($routes as $route) {
            $uri = $route->uri();

            // Skip internal test/debug routes
            if (str_starts_with($uri, '_boost') || str_starts_with($uri, '_ignition')) {
                continue;
            }

            $openApiPath = '/'.ltrim($uri, '/');
            $methods = array_values(array_diff($route->methods(), ['HEAD']));

            $module = $this->resolveModuleForRoute($route);
            $key = RouteAuthorizationScanner::routeKey($route);
            $mapped = $roleMap[$key] ?? null;
            $roles = $this->resolveAllowedRoles($mapped, $route);

            foreach ($methods as $method) {
                $methodLower = strtolower($method);
                $opId = $this->generateOperationId($methodLower, $openApiPath, $route->getName(), $seenOperationIds);
                $seenOperationIds[$opId] = true;

                $paths[$openApiPath][$methodLower] = $this->buildOperationSpec(
                    $methodLower,
                    $openApiPath,
                    $route,
                    $module,
                    $roles,
                    $opId
                );
            }
        }

        ksort($paths);

        return $paths;
    }

    /**
     * Resolve module name from route controller or URI.
     */
    private function resolveModuleForRoute(Route $route): string
    {
        $action = $route->getActionName();

        if (preg_match('#Modules\\\\([A-Za-z0-9]+)#', $action, $m)) {
            return $m[1];
        }

        $uri = ltrim($route->uri(), '/');

        if (str_starts_with($uri, 'api/v1/logistics') || str_starts_with($uri, 'track')) {
            return 'Logistics';
        }

        if (str_starts_with($uri, 'api/v1/integration')) {
            return 'Integration';
        }

        if (str_starts_with($uri, 'api/global-search')) {
            return 'Shared';
        }

        if (str_starts_with($uri, 'profile') || str_starts_with($uri, 'login') || str_starts_with($uri, 'register')) {
            return 'Core';
        }

        return 'Core';
    }

    /**
     * Resolve allowed roles list from route-roles.php and route middleware.
     *
     * @return list<string>
     */
    private function resolveAllowedRoles(mixed $mapped, Route $route): array
    {
        if (is_array($mapped) && isset($mapped['roles']) && is_array($mapped['roles']) && ! empty($mapped['roles'])) {
            $roles = array_values($mapped['roles']);
            sort($roles);

            return $roles;
        }

        if ($mapped === 'public') {
            return ['public'];
        }

        if ($mapped === 'auth') {
            return ['auth'];
        }

        if ($mapped === 'signed') {
            return ['signed'];
        }

        // Fallback: inspect route middleware
        $middleware = $route->gatherMiddleware();
        foreach ($middleware as $m) {
            if (is_string($m) && str_starts_with($m, 'role:')) {
                $roleStr = substr($m, 5);
                $roles = array_values(array_filter(explode(',', $roleStr)));
                sort($roles);

                return $roles;
            }
        }

        if (in_array('auth', $middleware, true)) {
            return ['auth'];
        }

        return ['public'];
    }

    /**
     * Build operation spec for a specific method and path.
     *
     * @param  list<string>  $roles
     * @return array<string, mixed>
     */
    private function buildOperationSpec(
        string $method,
        string $path,
        Route $route,
        string $module,
        array $roles,
        string $operationId
    ): array {
        $summary = $this->generateSummary($method, $path, $route->getActionName(), $module);
        $parameters = $this->buildParameters($path, $method);

        $operation = [
            'operationId' => $operationId,
            'tags' => [$module],
            'summary' => $summary,
            'description' => "Operational endpoint for {$module} module. Protected by role matrix: [".implode(', ', $roles).'].',
            'x-allowed-roles' => $roles,
            'parameters' => $parameters,
            'responses' => $this->buildResponses($method),
            'security' => $this->buildSecurity($roles),
        ];

        if (in_array($method, ['post', 'put', 'patch'], true)) {
            $operation['requestBody'] = [
                'description' => "Request payload for {$operationId}",
                'required' => true,
                'content' => [
                    'application/json' => [
                        'schema' => [
                            'type' => 'object',
                            'description' => 'Dynamic payload attributes governed by module domain rules',
                        ],
                    ],
                ],
            ];
        }

        return $operation;
    }

    /**
     * @param  list<string>  $roles
     * @return list<array<string, list<string>>>
     */
    private function buildSecurity(array $roles): array
    {
        if ($roles === ['public']) {
            return [];
        }

        if (in_array('signed', $roles, true)) {
            return [
                ['HmacWebhookSignature' => []],
            ];
        }

        return [
            ['BearerAuth' => []],
            ['SessionCookieAuth' => []],
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function buildResponses(string $method): array
    {
        $successCode = ($method === 'post') ? '201' : '200';
        $successDesc = ($method === 'post') ? 'Resource successfully created or operation executed' : 'Successful operation';

        return [
            $successCode => [
                'description' => $successDesc,
                'content' => [
                    'application/json' => [
                        'schema' => [
                            'type' => 'object',
                            'description' => 'Response envelope',
                        ],
                    ],
                ],
            ],
            '401' => [
                'description' => 'Authentication credentials missing, expired, or invalid',
                'content' => [
                    'application/problem+json' => [
                        'schema' => [
                            '$ref' => '#/components/schemas/ProblemDetailsRfc7807',
                        ],
                    ],
                ],
            ],
            '403' => [
                'description' => 'Access forbidden: caller does not possess required roles defined in x-allowed-roles',
                'content' => [
                    'application/problem+json' => [
                        'schema' => [
                            '$ref' => '#/components/schemas/ProblemDetailsRfc7807',
                        ],
                    ],
                ],
            ],
            '409' => [
                'description' => 'Conflict or idempotent replay detected with mismatching payload or concurrency conflict',
                'content' => [
                    'application/problem+json' => [
                        'schema' => [
                            '$ref' => '#/components/schemas/ProblemDetailsRfc7807',
                        ],
                    ],
                ],
            ],
            '422' => [
                'description' => 'Validation error or business invariant domain violation',
                'content' => [
                    'application/problem+json' => [
                        'schema' => [
                            '$ref' => '#/components/schemas/ProblemDetailsRfc7807',
                        ],
                    ],
                ],
            ],
            '429' => [
                'description' => 'Rate limit exceeded: caller exceeded request quota per time window',
                'content' => [
                    'application/problem+json' => [
                        'schema' => [
                            '$ref' => '#/components/schemas/ProblemDetailsRfc7807',
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buildParameters(string $path, string $method): array
    {
        $params = [];

        // Extract path parameters from URI (e.g., {booking}, {id}, {slug})
        if (preg_match_all('#\{([a-zA-Z0-9_]+)\}#', $path, $matches)) {
            foreach ($matches[1] as $paramName) {
                $params[] = [
                    'name' => $paramName,
                    'in' => 'path',
                    'required' => true,
                    'description' => "Identifier parameter {$paramName} for this resource",
                    'schema' => [
                        'type' => 'string',
                    ],
                ];
            }
        }

        // Common correlation header
        $params[] = [
            '$ref' => '#/components/parameters/CorrelationIdHeader',
        ];

        // Idempotency key for mutation methods
        if (in_array($method, ['post', 'put', 'patch', 'delete'], true)) {
            $params[] = [
                '$ref' => '#/components/parameters/IdempotencyKeyHeader',
            ];
        }

        // Keyset pagination for collection GETs
        if ($method === 'get' && ! str_contains($path, '{')) {
            $params[] = [
                '$ref' => '#/components/parameters/CursorPaginationParam',
            ];
        }

        return $params;
    }

    /**
     * Generate deterministic operationId.
     *
     * @param  array<string, bool>  $seenOperationIds
     */
    private function generateOperationId(string $method, string $path, ?string $routeName, array $seenOperationIds): string
    {
        if ($routeName) {
            $cleanName = Str::camel(str_replace(['.', '-', ':'], '_', $routeName));
            $baseId = $method.ucfirst($cleanName);
        } else {
            $cleanPath = preg_replace('#[^a-zA-Z0-9]#', '_', trim($path, '/'));
            $baseId = $method.Str::studly((string) $cleanPath);
        }

        $candidate = $baseId;
        $counter = 1;
        while (isset($seenOperationIds[$candidate])) {
            $candidate = "{$baseId}_{$counter}";
            $counter++;
        }

        return $candidate;
    }

    private function generateSummary(string $method, string $path, string $action, string $module): string
    {
        if (str_contains($action, '@')) {
            $methodName = explode('@', $action)[1];
            $words = Str::headline($methodName);

            return "{$words} ({$module})";
        }

        $cleanPath = trim(preg_replace('#\{[^\}]+\}#', '', $path), '/');
        $segments = array_filter(explode('/', $cleanPath));
        $lastSegment = end($segments) ?: 'root';

        return strtoupper($method).' '.Str::headline($lastSegment)." in {$module}";
    }

    /**
     * @return array<string, mixed>
     */
    private function loadRouteRolesMap(): array
    {
        $path = base_path('tests/Architecture/route-roles.php');
        if (file_exists($path)) {
            /** @var array<string, mixed> $map */
            $map = require $path;

            return $map;
        }

        return [];
    }

    public function toJson(): string
    {
        return json_encode(
            $this->buildSpec(),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        )."\n";
    }

    public function toYaml(): string
    {
        return Yaml::dump(
            $this->buildSpec(),
            10,
            2,
            Yaml::DUMP_OBJECT_AS_MAP | Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK
        );
    }

    /**
     * Write generated OpenAPI 3.1 specs to disk.
     *
     * @return array{json: string, yaml: string, explorer: string}
     */
    public function writeFiles(?string $basePath = null): array
    {
        $root = $basePath ?? base_path();
        $docsDir = $root.'/docs';

        if (! is_dir($docsDir)) {
            mkdir($docsDir, 0755, true);
        }

        $jsonPath = $docsDir.'/openapi-v3.json';
        $yamlPath = $docsDir.'/openapi-v3.yaml';
        $explorerPath = $docsDir.'/api-explorer.html';

        $spec = $this->buildSpec();
        $jsonContent = json_encode($spec, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n";
        $yamlContent = Yaml::dump($spec, 10, 2, Yaml::DUMP_OBJECT_AS_MAP | Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK);
        $explorerContent = InteractiveApiExplorer::renderHtml($spec);

        file_put_contents($jsonPath, $jsonContent);
        file_put_contents($yamlPath, $yamlContent);
        file_put_contents($explorerPath, $explorerContent);

        return [
            'json' => $jsonPath,
            'yaml' => $yamlPath,
            'explorer' => $explorerPath,
        ];
    }

    /**
     * Check if files on disk are synchronized with current route registry.
     */
    public function isSynchronized(?string $basePath = null): bool
    {
        $root = $basePath ?? base_path();
        $jsonPath = $root.'/docs/openapi-v3.json';
        $yamlPath = $root.'/docs/openapi-v3.yaml';

        if (! file_exists($jsonPath) || ! file_exists($yamlPath)) {
            return false;
        }

        $spec = $this->buildSpec();
        $expectedJson = json_encode($spec, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n";
        $currentJson = file_get_contents($jsonPath);

        return trim($expectedJson) === trim((string) $currentJson);
    }
}

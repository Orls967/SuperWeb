<?php

declare(strict_types=1);

namespace Tests\Perilaku;

use App\Models\User;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Modules\Core\Domain\Models\Role;
use Modules\Core\Domain\Models\Vehicle;
use Modules\Crypto\Domain\Models\CryptoAsset;
use Modules\Logistics\Domain\Models\Shipment;
use Modules\Payment\Domain\Models\PaymentIntent;
use Modules\Resto\Domain\Models\CateringOrder;
use Modules\Resto\Domain\Models\Delivery;
use Modules\Resto\Domain\Models\Ingredient;
use Modules\Resto\Domain\Models\PurchaseOrder;
use Modules\Resto\Domain\Models\StockCount;
use Modules\Resto\Domain\Models\StockTransfer;
use Modules\Store\Domain\Models\Product;
use PHPUnit\Framework\Attributes\Group;
use ReflectionClass;
use ReflectionFunction;
use Tests\TestCase;
use Throwable;

#[Group('perilaku')]
class RouteSmokeTest extends TestCase
{
    /** @var array<string, User> */
    protected static array $userCache = [];

    protected function setUp(): void
    {
        parent::setUp();

        config(['database.connections.sqlite.database' => database_path('database.sqlite')]);
        DB::purge('sqlite');
        DB::reconnect('sqlite');
    }

    /**
     * Resolves the module name for a route.
     */
    protected function resolveModule(LaravelRoute $route): string
    {
        $action = $route->getActionName();
        if (preg_match('/Modules\\\\([^\\\\]+)/', $action, $m)) {
            return $m[1];
        }

        $controller = $route->getController();
        if ($controller) {
            $ref = new ReflectionClass($controller);
            if (preg_match('/modules\/([^\/]+)/i', $ref->getFileName() ?: '', $m)) {
                return $m[1];
            }
        }

        $actionArray = $route->getAction();
        if (isset($actionArray['uses']) && $actionArray['uses'] instanceof Closure) {
            $ref = new ReflectionFunction($actionArray['uses']);
            if (preg_match('/modules\/([^\/]+)/i', $ref->getFileName() ?: '', $m)) {
                return $m[1];
            }
        }

        // Check uri prefix
        $uri = trim($route->uri(), '/');
        $segments = explode('/', $uri);
        $first = $segments[0] ?? '';

        $knownModulePrefixes = [
            'agri' => 'Agri',
            'b2b' => 'B2b',
            'cloud-kitchen' => 'CloudKitchen',
            'control-tower' => 'ControlTower',
            'contracts' => 'Contract',
            'education' => 'Edu',
            'energy' => 'Egy',
            'epc' => 'Epc',
            'esg' => 'Esg',
            'ev' => 'Ev',
            'fleet' => 'Fleet',
            'hcm' => 'Hcm',
            'hospital' => 'Hospital',
            'hotel' => 'Hotel',
            'insurance' => 'Insurance',
            'integration' => 'Integration',
            'intercompany' => 'Intercompany',
            'international' => 'International',
            'logistics' => 'Logistics',
            'mall' => 'Mall',
            'manufacturing' => 'Manufacturing',
            'media' => 'Med',
            'mining' => 'Mining',
            'partners' => 'Partner',
            'partner' => 'Partner',
            'party' => 'Party',
            'pricing' => 'Pricing',
            'procurement' => 'Procurement',
            'proptech' => 'Proptech',
            'resto' => 'Resto',
            'retail' => 'Ret',
            'rwa' => 'Rwa',
            'store' => 'Store',
            'suppliers' => 'Supplier',
            'telematics' => 'Telematics',
            'telecom' => 'Tlx',
            'trade-finance' => 'TradeFinance',
            'trade' => 'Trade',
            'treasury' => 'Treasury',
            'vending' => 'Vending',
            'venue' => 'Venue',
            'wealth' => 'Wealth',
            'wms' => 'Wms',
            'autodex' => 'AutoDex',
            'bookings' => 'AutoServe',
            'services' => 'AutoServe',
            'spareparts' => 'AutoServe',
            'crypto' => 'Crypto',
            'admin/ledger' => 'Banking',
            'banking' => 'Banking',
            'pembiayaan' => 'Finance',
            'finance' => 'Finance',
            'assets' => 'Asset',
            'agency' => 'Agency',
            'distribution' => 'Distribution',
            'payment' => 'Payment',
        ];

        foreach ($knownModulePrefixes as $prefix => $mod) {
            if (str_starts_with($uri, $prefix)) {
                return $mod;
            }
        }

        if (str_starts_with($action, 'App\\') || in_array($first, ['login', 'register', 'dashboard', 'profile', 'ops', 'up', 'activity', 'notifications', 'sanctum', ''])) {
            return 'Core';
        }

        return 'Core';
    }

    /**
     * Resolves allowed roles from route middleware.
     *
     * @return array<string>
     */
    protected function resolveAllowedRoles(LaravelRoute $route): array
    {
        $middleware = $route->gatherMiddleware();
        $roles = [];

        foreach ($middleware as $m) {
            if (is_string($m) && str_starts_with($m, 'role:')) {
                $rolePart = substr($m, 5);
                foreach (explode(',', $rolePart) as $r) {
                    $r = trim($r);
                    if ($r !== '') {
                        $roles[] = $r;
                    }
                }
            }
        }

        $roles = array_unique($roles);
        if (! empty($roles)) {
            return array_values($roles);
        }

        // If no role: middleware, check if auth is required
        if (in_array('auth', $middleware)) {
            return ['admin']; // Default to admin for general authenticated routes
        }

        // Public / guest route
        return ['guest'];
    }

    /**
     * Obtains or creates a user for the given role.
     */
    protected function getUserForRole(string $role): ?User
    {
        if ($role === 'guest') {
            return null;
        }

        if (isset(self::$userCache[$role])) {
            return self::$userCache[$role];
        }

        $user = User::where('role', $role)->first();
        if (! $user) {
            $user = User::factory()->create([
                'name' => "User {$role}",
                'email' => "user_{$role}_test@example.com",
                'role' => $role,
                'email_verified_at' => now(),
            ]);

            $roleModel = Role::where('name', $role)->first();
            if ($roleModel) {
                $user->assignRbacRole($roleModel);
            }
        }

        self::$userCache[$role] = $user;

        return $user;
    }

    /**
     * Resolves parameters for a given route using real seeded records.
     * Returns array [resolvedUri, errorReason].
     *
     * @return array{0: string|null, 1: string|null}
     */
    protected function resolveRouteUri(LaravelRoute $route): array
    {
        $uri = $route->uri();
        $params = $route->parameterNames();

        if (empty($params)) {
            return ['/'.$uri, null];
        }

        $modelParams = $route->signatureParameters(Model::class);
        $replacements = [];

        foreach ($params as $idx => $param) {
            $val = null;

            // 1. Try matching with model parameters by name or single parameter position
            foreach ($modelParams as $p) {
                if ($p->getName() === $param || count($params) === 1) {
                    $cls = $p->getType() ? $p->getType()->getName() : null;
                    if ($cls) {
                        if (interface_exists($cls) && $cls === 'Modules\Store\Contracts\Product') {
                            $cls = 'Modules\Store\Domain\Models\Product';
                        }
                        if (class_exists($cls)) {
                            $rec = $cls::first();
                            if ($rec) {
                                $val = (string) ($rec->getRouteKey() ?? $rec->getKey());
                                break;
                            }
                        }
                    }
                }
            }

            // 2. Specific fallback resolvers for non-typehinted parameters
            if ($val === null) {
                if ($param === 'uuid') {
                    if (str_contains($uri, 'passport')) {
                        $val = Vehicle::first()?->uuid;
                    } elseif (str_contains($uri, 'payment')) {
                        $val = PaymentIntent::first()?->uuid;
                    } else {
                        $val = Vehicle::first()?->uuid;
                    }
                } elseif ($param === 'slug') {
                    $val = Product::whereNotNull('slug')->first()?->slug;
                } elseif ($param === 'symbol') {
                    $val = CryptoAsset::first()?->symbol;
                } elseif ($param === 'tracking_number') {
                    $val = Shipment::first()?->tracking_number;
                } elseif ($param === 'ingredient') {
                    $val = (string) (Ingredient::first()?->id ?? '');
                    if ($val === '') {
                        $val = null;
                    }
                } elseif ($param === 'purchase') {
                    $val = (string) (PurchaseOrder::first()?->id ?? '');
                    if ($val === '') {
                        $val = null;
                    }
                } elseif ($param === 'transfer') {
                    $val = (string) (StockTransfer::first()?->id ?? '');
                    if ($val === '') {
                        $val = null;
                    }
                } elseif ($param === 'stock_count') {
                    $val = (string) (StockCount::first()?->id ?? '');
                    if ($val === '') {
                        $val = null;
                    }
                } elseif ($param === 'delivery') {
                    $val = (string) (Delivery::first()?->id ?? '');
                    if ($val === '') {
                        $val = null;
                    }
                } elseif ($param === 'catering') {
                    $val = (string) (CateringOrder::first()?->id ?? '');
                    if ($val === '') {
                        $val = null;
                    }
                } elseif ($param === 'id') {
                    if (str_contains($uri, 'inventory/explorer')) {
                        $val = (string) (Product::first()?->id ?? '');
                    } elseif (str_contains($uri, 'mall/invoices') || str_contains($uri, 'mall/portal/invoices')) {
                        $val = (string) (DB::table('mall_invoices')->first()?->id ?? '');
                    } elseif (str_contains($uri, 'logistics/shipments')) {
                        $val = (string) (Shipment::first()?->id ?? '');
                    } elseif (str_contains($uri, 'energy/ppa')) {
                        $val = (string) (DB::table('egy_ppa_contracts')->first()?->id ?? '');
                    } elseif (str_contains($uri, 'media/studios')) {
                        $val = (string) (DB::table('med_studios')->first()?->id ?? '');
                    } elseif (str_contains($uri, 'media/licensing')) {
                        $val = (string) (DB::table('med_ip_licenses')->first()?->id ?? '');
                    } elseif (str_contains($uri, 'education/cohorts')) {
                        $val = (string) (DB::table('edu_cohorts')->first()?->id ?? '');
                    } elseif (str_contains($uri, 'education/grants')) {
                        $val = (string) (DB::table('edu_research_grants')->first()?->id ?? '');
                    } elseif (str_contains($uri, 'retail/orders')) {
                        $val = (string) (DB::table('ret_orders')->first()?->id ?? '');
                    }
                    if ($val === '') {
                        $val = null;
                    }
                }
            }

            if ($val === null || $val === '') {
                return [null, "tidak teruji: parameter ({$param})"];
            }

            $replacements['{'.$param.'}'] = $val;
            $replacements['{'.$param.'?}'] = $val;
        }

        $resolved = str_replace(array_keys($replacements), array_values($replacements), $uri);

        return ['/'.ltrim($resolved, '/'), null];
    }

    public function test_run_get_route_smoke_map(): void
    {
        $getRoutes = collect(Route::getRoutes())->filter(fn (LaravelRoute $r) => in_array('GET', $r->methods()));

        $results = [];
        $untested = [];
        $statusCounts = [];
        $moduleStats = [];
        $errors500 = [];
        $slowPages = [];

        foreach ($getRoutes as $route) {
            $uriPattern = $route->uri();
            $module = $this->resolveModule($route);
            $roles = $this->resolveAllowedRoles($route);

            [$resolvedUri, $untestedReason] = $this->resolveRouteUri($route);

            if ($untestedReason !== null) {
                $untested[] = [
                    'uri_pattern' => $uriPattern,
                    'module' => $module,
                    'reason' => $untestedReason,
                    'roles' => $roles,
                ];

                if (! isset($moduleStats[$module])) {
                    $moduleStats[$module] = ['total' => 0, 'tested' => 0, 'untested' => 0, '2xx' => 0, '3xx' => 0, '4xx' => 0, '500' => 0];
                }
                $moduleStats[$module]['total']++;
                $moduleStats[$module]['untested']++;

                continue;
            }

            // Test for each allowed role
            foreach ($roles as $role) {
                $user = $this->getUserForRole($role);

                DB::flushQueryLog();
                DB::enableQueryLog();
                $startTime = microtime(true);

                $status = null;
                $body = '';
                $exceptionMsg = null;
                $exceptionFile = null;
                $exceptionLine = null;

                try {
                    $testResponse = $user
                        ? $this->actingAs($user)->get($resolvedUri)
                        : $this->get($resolvedUri);

                    $durationMs = round((microtime(true) - $startTime) * 1000, 2);
                    $queries = DB::getQueryLog();
                    $status = $testResponse->getStatusCode();
                    $body = (string) $testResponse->getContent();

                    if ($status >= 500 && isset($testResponse->exception)) {
                        $exceptionMsg = get_class($testResponse->exception).': '.$testResponse->exception->getMessage();
                        $exceptionFile = $testResponse->exception->getFile();
                        $exceptionLine = $testResponse->exception->getLine();
                    }
                } catch (Throwable $e) {
                    $durationMs = round((microtime(true) - $startTime) * 1000, 2);
                    $queries = DB::getQueryLog();
                    $status = 500;
                    $body = $e->getMessage();
                    $exceptionMsg = get_class($e).': '.$e->getMessage();
                    $exceptionFile = $e->getFile();
                    $exceptionLine = $e->getLine();

                    Log::error("Smoke 500 on GET {$resolvedUri} [role: {$role}]: {$exceptionMsg} at {$exceptionFile}:{$exceptionLine}");
                }

                $queryCount = count($queries);
                $bodySize = strlen($body);

                $hasErrorText = false;
                foreach (['Whoops', 'Exception', 'Undefined', 'SQLSTATE'] as $needle) {
                    if (stripos($body, $needle) !== false) {
                        $hasErrorText = true;
                        break;
                    }
                }

                $pageTitle = '';
                if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $body, $mTitle)) {
                    $pageTitle = trim(html_entity_decode(strip_tags($mTitle[1])));
                }

                $item = [
                    'uri_pattern' => $uriPattern,
                    'uri_tested' => $resolvedUri,
                    'module' => $module,
                    'role' => $role,
                    'status' => $status,
                    'duration_ms' => $durationMs,
                    'query_count' => $queryCount,
                    'body_size' => $bodySize,
                    'has_error_text' => $hasErrorText,
                    'page_title' => $pageTitle,
                    'exception' => $exceptionMsg ? [
                        'message' => $exceptionMsg,
                        'file' => $exceptionFile,
                        'line' => $exceptionLine,
                    ] : null,
                ];

                $results[] = $item;

                // Aggregate stats
                if (! isset($moduleStats[$module])) {
                    $moduleStats[$module] = ['total' => 0, 'tested' => 0, 'untested' => 0, '2xx' => 0, '3xx' => 0, '4xx' => 0, '500' => 0];
                }
                $moduleStats[$module]['total']++;
                $moduleStats[$module]['tested']++;

                if ($status >= 200 && $status < 300) {
                    $moduleStats[$module]['2xx']++;
                } elseif ($status >= 300 && $status < 400) {
                    $moduleStats[$module]['3xx']++;
                } elseif ($status >= 400 && $status < 500) {
                    $moduleStats[$module]['4xx']++;
                } elseif ($status >= 500) {
                    $moduleStats[$module]['500']++;
                    $errors500[] = $item;
                }

                $slowPages[] = [
                    'uri' => $resolvedUri,
                    'module' => $module,
                    'role' => $role,
                    'query_count' => $queryCount,
                    'duration_ms' => $durationMs,
                    'queries' => array_slice(array_map(fn ($q) => $q['query'], $queries), 0, 10),
                ];
            }
        }

        // Sort slow pages by query_count desc
        usort($slowPages, fn ($a, $b) => $b['query_count'] <=> $a['query_count']);
        $top20Slow = array_slice($slowPages, 0, 20);

        // Write raw to storage/audit/route-smoke.json
        File::ensureDirectoryExists(storage_path('audit'));
        File::put(storage_path('audit/route-smoke.json'), json_encode([
            'generated_at' => now()->toIso8601String(),
            'total_get_routes' => $getRoutes->count(),
            'total_executions' => count($results),
            'executions' => $results,
            'untested' => $untested,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        // Write summary to docs/audits/2026-10-30-perilaku-rute.json
        $summary = [
            'generated_at' => now()->toIso8601String(),
            'total_get_routes' => $getRoutes->count(),
            'total_executions' => count($results),
            'tested_routes_count' => count(array_unique(array_column($results, 'uri_pattern'))),
            'untested_routes_count' => count($untested),
            'untested_routes' => $untested,
            'module_stats' => $moduleStats,
            'errors_500_count' => count($errors500),
            'errors_500_first_30' => array_slice($errors500, 0, 30),
            'top_20_query_heavy' => $top20Slow,
        ];

        File::ensureDirectoryExists(base_path('docs/audits'));
        File::put(base_path('docs/audits/2026-10-30-perilaku-rute.json'), json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $this->assertNotEmpty($results, 'Route smoke test executed successfully');
    }
}

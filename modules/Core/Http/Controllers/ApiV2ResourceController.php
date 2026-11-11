<?php

declare(strict_types=1);

namespace Modules\Core\Http\Controllers;

use App\Http\Controllers\Controller;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\Application\Api\CursorPaginationService;
use Modules\Core\Application\Tenancy\TenantContext;
use Modules\Core\Contracts\ApiResourceProvider;

class ApiV2ResourceController extends Controller
{
    /** @var array<string, ApiResourceProvider> */
    private static array $providers = [];

    public function __construct(
        private readonly CursorPaginationService $paginationService,
    ) {}

    public static function registerProvider(ApiResourceProvider $provider): void
    {
        self::$providers[$provider->resourceKey()] = $provider;
    }

    public static function getProvider(string $key): ?ApiResourceProvider
    {
        return self::$providers[$key] ?? null;
    }

    public function index(Request $request, string $resource): JsonResponse
    {
        $provider = self::getProvider($resource);
        if ($provider === null) {
            return response()->json([
                'type' => 'https://superweb.example.com/probs/resource-not-found',
                'title' => 'Resource Not Found',
                'status' => 404,
                'detail' => "API v2 resource [{$resource}] does not exist.",
            ], 404);
        }

        $context = TenantContext::current() ?? new TenantContext('tenant-default');
        $query = $provider->baseQuery($context);

        // Apply declared filters
        foreach ($provider->declaredFilters() as $filterCol => $matchType) {
            if ($request->has($filterCol) && $request->input($filterCol) !== null) {
                $val = (string) $request->input($filterCol);
                if ($matchType === 'exact') {
                    $query->where($filterCol, '=', $val);
                } elseif ($matchType === 'like') {
                    $query->where($filterCol, 'LIKE', "%{$val}%");
                }
            }
        }

        // Apply sorts
        $sortCol = $provider->defaultSortColumn();
        if ($request->has('sort')) {
            $requestedSort = (string) $request->query('sort');
            if (!in_array($requestedSort, $provider->declaredSorts(), true)) {
                return response()->json([
                    'type' => 'https://superweb.example.com/probs/invalid-sort-parameter',
                    'title' => 'Invalid Sort Parameter',
                    'status' => 422,
                    'detail' => "Column [{$requestedSort}] is not allowed for sorting on [{$resource}].",
                    'instance' => $request->fullUrl(),
                ], 422, ['Content-Type' => 'application/problem+json']);
            }
            $sortCol = $requestedSort;
        }

        $direction = (string) $request->query('direction', 'asc');
        $limit = (int) $request->query('limit', CursorPaginationService::DEFAULT_LIMIT);
        $cursor = $request->query('cursor');

        $paginated = $this->paginationService->paginate(
            $query,
            $limit,
            $cursor,
            $sortCol,
            $direction
        );

        $mappedData = $paginated['data']->map(fn ($item) => $provider->mapRecord($item))->values();

        $response = response()->json([
            'data' => $mappedData,
            'meta' => $paginated['meta'],
        ]);

        if ($provider->deprecatedV1Path() !== null) {
            $response->headers->set('Deprecation', 'true');
            $response->headers->set('Link', sprintf('<%s>; rel="predecessor-version"', $provider->deprecatedV1Path()));
        }

        return $response;
    }

    public function show(Request $request, string $resource, string $id): JsonResponse
    {
        $provider = self::getProvider($resource);
        if ($provider === null) {
            return response()->json([
                'type' => 'https://superweb.example.com/probs/resource-not-found',
                'title' => 'Resource Not Found',
                'status' => 404,
            ], 404);
        }

        $context = TenantContext::current() ?? new TenantContext('tenant-default');
        $item = $provider->baseQuery($context)->find($id);

        if ($item === null) {
            return response()->json([
                'type' => 'https://superweb.example.com/probs/not-found',
                'title' => 'Item Not Found',
                'status' => 404,
                'detail' => "Item [{$id}] was not found in [{$resource}].",
            ], 404);
        }

        return response()->json([
            'data' => $provider->mapRecord($item),
        ]);
    }

    public function action(Request $request, string $resource, string $action): JsonResponse
    {
        $provider = self::getProvider($resource);
        if ($provider === null) {
            return response()->json([
                'type' => 'https://superweb.example.com/probs/resource-not-found',
                'title' => 'Resource Not Found',
                'status' => 404,
            ], 404);
        }

        $declaredActions = $provider->declaredActions();
        if (!isset($declaredActions[$action])) {
            return response()->json([
                'type' => 'https://superweb.example.com/probs/action-not-found',
                'title' => 'Action Not Found',
                'status' => 404,
                'detail' => "Action [{$action}] is not declared on [{$resource}].",
            ], 404);
        }

        $def = $declaredActions[$action];
        foreach ($def['required_fields'] as $field) {
            if (!$request->has($field) || $request->input($field) === null) {
                return response()->json([
                    'type' => 'https://superweb.example.com/probs/validation-error',
                    'title' => 'Missing Required Field',
                    'status' => 422,
                    'detail' => "Field [{$field}] is required for action [{$action}].",
                ], 422);
            }
        }

        $context = TenantContext::current() ?? new TenantContext('tenant-default');
        $idempKey = (string) $request->header('Idempotency-Key', uniqid());

        try {
            $result = $provider->executeAction($action, $request->all(), $idempKey, $context);
            return response()->json($result['data'], $result['status']);
        } catch (DomainException $e) {
            return response()->json([
                'type' => 'https://superweb.example.com/probs/domain-error',
                'title' => 'Domain Error',
                'status' => 422,
                'detail' => $e->getMessage(),
            ], 422);
        }
    }
}

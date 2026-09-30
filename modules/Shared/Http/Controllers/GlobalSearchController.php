<?php

declare(strict_types=1);

namespace Modules\Shared\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Shared\Application\Queries\GlobalSearchQuery;

class GlobalSearchController extends Controller
{
    public function __invoke(Request $request, GlobalSearchQuery $query): JsonResponse
    {
        $q = (string) $request->input('q', '');
        $results = $query->search($q, $request->user());

        return response()->json([
            'query' => $q,
            'count' => count($results),
            'results' => $results,
        ]);
    }
}

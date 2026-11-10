<?php

declare(strict_types=1);

namespace Modules\Core\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\Application\Actions\Search\SearchOrchestratorService;

class GlobalSearchApiController extends Controller
{
    public function __invoke(Request $request, SearchOrchestratorService $searchOrchestrator): JsonResponse
    {
        $q = (string) $request->input('q', '');
        $results = $searchOrchestrator->search($q, $request->user());

        return response()->json($results);
    }
}

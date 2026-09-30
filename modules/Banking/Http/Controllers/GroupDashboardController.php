<?php

declare(strict_types=1);

namespace Modules\Banking\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Banking\Application\Queries\ConsolidatedPlQuery;

class GroupDashboardController extends Controller
{
    public function __invoke(Request $request, ConsolidatedPlQuery $query): View|JsonResponse
    {
        $days = (int) $request->input('days', 30);
        $data = $query->execute($days);

        if ($request->wantsJson()) {
            return response()->json($data);
        }

        return view('banking::admin.group-dashboard', $data);
    }
}

<?php

declare(strict_types=1);

namespace Modules\Insurance\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Insurance\Application\Services\CustomerHubService;

final class CustomerHubController extends Controller
{
    public function dashboard(Request $request, CustomerHubService $hubService)
    {
        $user = $request->user();
        $partyId = (string) ($request->query('customer_id') ?? $user?->party_id ?? $user?->id ?? '1');

        $portfolio = $hubService->getPortfolio($partyId);

        return view('insurance::hub.dashboard', compact('portfolio'));
    }
}

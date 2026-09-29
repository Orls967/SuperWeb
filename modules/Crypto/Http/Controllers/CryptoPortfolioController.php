<?php

declare(strict_types=1);

namespace Modules\Crypto\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Crypto\Application\Services\PortfolioService;

class CryptoPortfolioController extends Controller
{
    public function __construct(
        private readonly PortfolioService $portfolioService
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $summary = $this->portfolioService->getPortfolioSummary($user);

        return view('crypto::portfolio.index', [
            'summary' => $summary,
        ]);
    }
}

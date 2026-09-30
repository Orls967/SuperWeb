<?php

declare(strict_types=1);

namespace Modules\Logistics\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LogisticsDashboardController extends Controller
{
    public function index(Request $request): View
    {
        return view('logistics::dashboard', [
            'user' => $request->user(),
        ]);
    }
}

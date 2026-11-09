<?php

declare(strict_types=1);

namespace Modules\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\View\View;
use Modules\Platform\Domain\Models\ServiceSlo;
use Modules\Platform\Http\ViewModels\SloMonitorViewModel;

class SloTrackerController extends Controller
{
    public function index(): View
    {
        $slos = ServiceSlo::all();
        $viewModel = new SloMonitorViewModel($slos);

        return view('platform::dev.slos.index', [
            'viewModel' => $viewModel,
            'slos' => $slos,
        ]);
    }
}

<?php

declare(strict_types=1);

namespace Modules\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\View\View;
use Modules\Platform\Domain\Models\PhaseMetricSnapshot;
use Modules\Platform\Http\ViewModels\QualityMetricsViewModel;

class PhaseMetricsController extends Controller
{
    public function index(): View
    {
        $metrics = PhaseMetricSnapshot::latest()->paginate(20);
        $viewModel = new QualityMetricsViewModel($metrics->getCollection());

        return view('platform::dev.metrics.index', [
            'viewModel' => $viewModel,
            'metrics' => $metrics,
        ]);
    }
}

<?php

declare(strict_types=1);

namespace Modules\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Platform\Domain\Models\ReleaseTrain;
use Modules\Platform\Http\ViewModels\ReleaseTrainViewModel;

class ReleaseTrainController extends Controller
{
    public function index(Request $request): View
    {
        $trains = ReleaseTrain::with(['releaseManager', 'changeRequests', 'hotfixes'])
            ->latest()
            ->paginate(15);

        $viewModel = new ReleaseTrainViewModel($trains->getCollection());

        return view('platform::release.index', [
            'trains' => $trains,
            'viewModel' => $viewModel,
        ]);
    }

    public function show(ReleaseTrain $train): View
    {
        $train->load(['releaseManager', 'changeRequests', 'hotfixes', 'deployments.observations']);

        return view('platform::release.show', [
            'train' => $train,
        ]);
    }
}

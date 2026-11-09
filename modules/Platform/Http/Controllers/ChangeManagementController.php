<?php

declare(strict_types=1);

namespace Modules\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Platform\Domain\Models\ChangeRequest;
use Modules\Platform\Http\ViewModels\ChangeManagementViewModel;

class ChangeManagementController extends Controller
{
    public function index(Request $request): View
    {
        $changes = ChangeRequest::with(['requester', 'releaseTrain'])
            ->latest()
            ->paginate(15);

        $viewModel = new ChangeManagementViewModel($changes->getCollection());

        return view('platform::change.index', [
            'changes' => $changes,
            'viewModel' => $viewModel,
        ]);
    }

    public function show(ChangeRequest $change): View
    {
        $change->load(['requester', 'releaseTrain', 'transitions.operator', 'rollbackPlan']);

        return view('platform::change.show', [
            'change' => $change,
        ]);
    }
}

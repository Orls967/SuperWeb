<?php

declare(strict_types=1);

namespace Modules\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Platform\Domain\Models\SandboxEnvironment;
use Modules\Platform\Http\ViewModels\SandboxDetailViewModel;

class SandboxController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request): View
    {
        $sandboxes = SandboxEnvironment::where('owner_id', $request->user()->id)
            ->latest()
            ->paginate(15);

        return view('platform::dev.sandboxes.index', [
            'sandboxes' => $sandboxes,
        ]);
    }

    public function show(SandboxEnvironment $sandbox): View
    {
        $this->authorize('view', $sandbox);

        $sandbox->load('transitions');
        $viewModel = new SandboxDetailViewModel($sandbox);

        return view('platform::dev.sandboxes.show', [
            'viewModel' => $viewModel,
            'sandbox' => $sandbox,
        ]);
    }
}

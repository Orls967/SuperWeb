<?php

declare(strict_types=1);

namespace Modules\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Platform\Domain\Models\DataBackfill;
use Modules\Platform\Http\ViewModels\DataBackfillViewModel;

class DataBackfillController extends Controller
{
    public function index(Request $request): View
    {
        $backfills = DataBackfill::with('chunks')
            ->latest()
            ->paginate(15);

        $viewModel = new DataBackfillViewModel($backfills->getCollection());

        return view('platform::backfills.index', [
            'backfills' => $backfills,
            'viewModel' => $viewModel,
        ]);
    }
}

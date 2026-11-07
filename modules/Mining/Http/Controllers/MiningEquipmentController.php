<?php

declare(strict_types=1);

namespace Modules\Mining\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Mining\Http\Policies\MiningFase93AccessPolicy;
use Modules\Mining\Http\ViewModels\MiningFase93OperationsViewModel;

class MiningEquipmentController extends Controller
{
    public function index(
        Request $request,
        MiningFase93OperationsViewModel $viewModel,
        MiningFase93AccessPolicy $policy
    ): View {
        $policy->authorizeEquipmentAccess($request->user());

        $siteId = $request->query('site_id') ? (string) $request->query('site_id') : null;

        return view('mining::equipment.index', $viewModel->buildEquipmentViewData($siteId));
    }
}

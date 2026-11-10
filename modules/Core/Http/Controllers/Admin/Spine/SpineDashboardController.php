<?php

declare(strict_types=1);

namespace Modules\Core\Http\Controllers\Admin\Spine;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Modules\Core\Application\Services\SpineLagMonitorService;
use Modules\Core\Domain\Models\CoreEventDelivery;
use Modules\Core\Domain\Models\CoreEventSchema;
use Modules\Core\Domain\Models\CorePoisonMessage;
use Modules\Core\Domain\Models\CoreSpineGap;
use Modules\Core\Domain\Models\SimEventSpine;
use Modules\Core\Domain\StateMachines\EventDeliveryStateMachine;

class SpineDashboardController extends Controller
{
    public function __invoke(Request $request, SpineLagMonitorService $lagMonitor): View
    {
        $totalEvents = SimEventSpine::count();
        $totalDeliveries = CoreEventDelivery::count();
        $deadLetters = CoreEventDelivery::where('status', EventDeliveryStateMachine::STATUS_DEAD_LETTERED)->count();
        $poisonMessages = CorePoisonMessage::count();
        $openGaps = CoreSpineGap::where('status', CoreSpineGap::STATUS_OPEN)->count();
        $registeredSchemas = CoreEventSchema::count();
        $overallP95Lag = $lagMonitor->getOverallP95Lag();

        $recentDeliveries = CoreEventDelivery::orderBy('updated_at', 'desc')->limit(10)->get();

        return view('core::admin.spine.index', compact(
            'totalEvents',
            'totalDeliveries',
            'deadLetters',
            'poisonMessages',
            'openGaps',
            'registeredSchemas',
            'overallP95Lag',
            'recentDeliveries'
        ));
    }
}

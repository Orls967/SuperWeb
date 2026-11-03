<?php

declare(strict_types=1);

namespace Modules\Port\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Port\Domain\Models\Berth;
use Modules\Port\Domain\Models\TerminalBill;
use Modules\Port\Domain\Models\VesselCall;
use Modules\Port\Domain\Models\YardBlock;
use Modules\Port\Domain\Models\YardSlot;

class PortController extends Controller
{
    public function index(): View
    {
        $berthsCount = Berth::count();
        $activeCallsCount = VesselCall::whereIn('status', ['SCHEDULED', 'AT_BERTH'])->count();
        $yardOccupancy = YardSlot::where('status', 'IN_YARD')->count();
        $totalBills = TerminalBill::sum('amount_idr');

        return view('port::index', compact('berthsCount', 'activeCallsCount', 'yardOccupancy', 'totalBills'));
    }

    public function berths(): View
    {
        $berths = Berth::with(['vesselCalls' => function ($q) {
            $q->orderBy('id');
        }])->get();

        $scheduledCalls = VesselCall::where('status', 'SCHEDULED')->get();

        return view('port::berths', compact('berths', 'scheduledCalls'));
    }

    public function yard(): View
    {
        $blocks = YardBlock::with(['slots' => function ($q) {
            $q->where('status', 'IN_YARD');
        }])->get();

        return view('port::yard', compact('blocks'));
    }

    public function billing(Request $request): View
    {
        $user = $request->user();
        $query = TerminalBill::with('vesselCall');

        // IDOR line party isolation: jika user adalah shipping line agent, hanya lihat tagihan miliknya
        if ($user && $user->role === 'shipping_line_agent') {
            $query->where('line_party_id', $user->id);
        }

        $bills = $query->latest('id')->paginate(20);

        return view('port::billing', compact('bills'));
    }
}

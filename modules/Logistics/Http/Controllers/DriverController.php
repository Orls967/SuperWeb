<?php

declare(strict_types=1);

namespace Modules\Logistics\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Logistics\Domain\Models\Driver;

class DriverController extends Controller
{
    public function index(Request $request): View
    {
        $query = Driver::with(['user', 'homeHub']);

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->filled('license_class')) {
            $query->where('license_class', $request->query('license_class'));
        }

        if ($request->filled('q')) {
            $search = '%'.$request->query('q').'%';
            $query->where(function ($q) use ($search) {
                $q->where('driver_number', 'like', $search)
                    ->orWhereHas('user', fn ($uq) => $uq->where('name', 'like', $search)->orWhere('phone', 'like', $search));
            });
        }

        $drivers = $query->paginate(20)->withQueryString();

        return view('logistics::drivers.index', [
            'drivers' => $drivers,
            'filters' => $request->only(['status', 'license_class', 'q']),
        ]);
    }
}

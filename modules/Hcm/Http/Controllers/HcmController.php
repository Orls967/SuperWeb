<?php

declare(strict_types=1);

namespace Modules\Hcm\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Hcm\Domain\Models\Employee;
use Modules\Hcm\Domain\Models\Payroll;

class HcmController extends Controller
{
    public function index(Request $request)
    {
        $employees = Employee::with('department')->latest()->take(20)->get();
        $payrolls = Payroll::with('employee')->latest()->take(20)->get();

        return view('hcm::hcm.index', [
            'employees' => $employees,
            'payrolls' => $payrolls,
        ]);
    }
}

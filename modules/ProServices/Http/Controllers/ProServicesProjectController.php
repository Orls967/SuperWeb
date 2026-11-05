<?php

declare(strict_types=1);

namespace Modules\ProServices\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\ProServices\Domain\Models\ProServicesInvoice;
use Modules\ProServices\Domain\Models\ProServicesProject;
use Modules\ProServices\Domain\Models\ProServicesTimesheet;

final class ProServicesProjectController extends Controller
{
    public function indexProjects(Request $request)
    {
        $user = $request->user();
        if (! $user || ! in_array($user->role, ['project_manager', 'admin', 'superadmin'], true)) {
            abort(403, 'Unauthorized');
        }

        $projects = ProServicesProject::with('members')->paginate(20);

        return response()->view('proservices::projects', ['projects' => $projects]);
    }

    public function indexTimesheets(Request $request)
    {
        $user = $request->user();
        if (! $user || ! in_array($user->role, ['consultant', 'admin'], true)) {
            abort(403, 'Unauthorized');
        }

        $timesheets = ProServicesTimesheet::whereHas('member', function ($q) use ($user) {
            $q->where('employee_ref', $user->id);
        })->paginate(20);

        return response()->view('proservices::timesheets', ['timesheets' => $timesheets]);
    }

    public function showTimesheet(Request $request, int $id)
    {
        $user = $request->user();
        $ts = ProServicesTimesheet::with('member')->findOrFail($id);

        if (! $user || ($user->role !== 'admin' && $ts->member->employee_ref !== $user->id)) {
            abort(403, 'Forbidden timesheet access');
        }

        return response()->view('proservices::timesheet_detail', ['timesheet' => $ts]);
    }

    public function indexInvoices(Request $request)
    {
        $user = $request->user();
        if (! $user || ! in_array($user->role, ['client', 'admin'], true)) {
            abort(403, 'Unauthorized');
        }

        $invoices = ProServicesInvoice::where('client_party_ref', $user->id)->paginate(20);

        return response()->view('proservices::invoices', ['invoices' => $invoices]);
    }

    public function showClientProject(Request $request, int $id)
    {
        $user = $request->user();
        $project = ProServicesProject::findOrFail($id);

        if (! $user || ($user->role !== 'admin' && $project->client_party_ref !== $user->id)) {
            abort(403, 'Forbidden client project access');
        }

        return response()->view('proservices::client_project_detail', ['project' => $project]);
    }
}

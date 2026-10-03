<?php

declare(strict_types=1);

namespace Modules\Core\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Modules\Core\Domain\Models\AuditLog;

class AuditLogController extends Controller
{
    /**
     * Display a searchable, filterable list of generic audit trail logs.
     */
    public function index(Request $request): View
    {
        $query = AuditLog::with('user')->latest('id');

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('action', 'like', "%{$search}%")
                    ->orWhere('correlation_id', 'like', "%{$search}%")
                    ->orWhere('ip_address', 'like', "%{$search}%")
                    ->orWhere('auditable_type', 'like', "%{$search}%")
                    ->orWhere('auditable_id', 'like', "%{$search}%");
            });
        }

        if ($action = $request->input('action')) {
            $query->where('action', $action);
        }

        if ($impactType = $request->input('impact_type')) {
            $query->where('impact_type', $impactType);
        }

        if ($userId = $request->input('user_id')) {
            $query->where('user_id', $userId);
        }

        if ($startDate = $request->input('start_date')) {
            $query->whereDate('created_at', '>=', $startDate);
        }

        if ($endDate = $request->input('end_date')) {
            $query->whereDate('created_at', '<=', $endDate);
        }

        $logs = $query->paginate(25)->withQueryString();

        $distinctActions = AuditLog::select('action')->distinct()->orderBy('action')->pluck('action');
        $distinctImpactTypes = AuditLog::whereNotNull('impact_type')->select('impact_type')->distinct()->pluck('impact_type');
        $users = User::orderBy('name')->get(['id', 'name', 'email']);

        return view('core::admin.audit_logs.index', compact(
            'logs',
            'distinctActions',
            'distinctImpactTypes',
            'users'
        ));
    }

    /**
     * Show detail of an audit log.
     */
    public function show(AuditLog $auditLog): View
    {
        $auditLog->load('user');

        return view('core::admin.audit_logs.show', compact('auditLog'));
    }
}

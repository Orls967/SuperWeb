<?php

declare(strict_types=1);

namespace Modules\Core\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Core\Application\Actions\TaskInbox\TaskDigestService;
use Modules\Core\Application\Actions\TaskInbox\TaskInboxService;
use Modules\Core\Domain\Models\CoreTask;
use Modules\Core\Http\Requests\ExecuteTaskActionRequest;

class TaskInboxController extends Controller
{
    public function __construct(
        protected TaskInboxService $inboxService,
        protected TaskDigestService $digestService,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $filters = [
            'status' => $request->input('status'),
            'priority' => $request->input('priority'),
            'module' => $request->input('module'),
        ];

        $tasks = $this->inboxService->getTasksForUser($user, $filters, 15);
        $summary = $this->digestService->generateDigestSummary($user->role === 'admin' ? null : $user->id);

        $availableModules = CoreTask::query()->select('module')->distinct()->pluck('module')->toArray();

        return view('core::task-inbox.index', [
            'tasks' => $tasks,
            'summary' => $summary,
            'filters' => $filters,
            'availableModules' => $availableModules,
        ]);
    }

    public function analytics(Request $request): View
    {
        return view('core::task-inbox.analytics');
    }

    public function show(string $id, Request $request): View
    {
        $task = CoreTask::query()->with('histories')->findOrFail($id);
        $user = $request->user();

        // Otorisasi: hanya pemilik tugas, peran penugasan, antrean umum tak bertuan, atau admin yang boleh melihat
        if ($user->role !== 'admin') {
            $isAssignedToUser = $task->assignee_user_id !== null && (string) $task->assignee_user_id === (string) $user->id;
            $isAssignedToRole = $task->assignee_role !== null && $task->assignee_role === $user->role;
            $isUnassigned = $task->assignee_user_id === null && $task->assignee_role === null;

            if (! $isAssignedToUser && ! $isAssignedToRole && ! $isUnassigned) {
                abort(403, 'Anda tidak memiliki hak akses terhadap tugas ini.');
            }
        }

        $delegatableUsers = User::query()->select(['id', 'name', 'email', 'role'])->limit(50)->get();

        return view('core::task-inbox.show', [
            'task' => $task,
            'delegatableUsers' => $delegatableUsers,
        ]);
    }

    public function action(string $id, ExecuteTaskActionRequest $request): RedirectResponse
    {
        $task = CoreTask::query()->findOrFail($id);
        $user = $request->user();

        if ($user->role !== 'admin') {
            $isAssignedToUser = $task->assignee_user_id !== null && (string) $task->assignee_user_id === (string) $user->id;
            $isAssignedToRole = $task->assignee_role !== null && $task->assignee_role === $user->role;
            $isUnassigned = $task->assignee_user_id === null && $task->assignee_role === null;

            if (! $isAssignedToUser && ! $isAssignedToRole && ! $isUnassigned) {
                abort(403, 'Anda tidak memiliki hak akses untuk memutasi tugas ini.');
            }
        }

        $act = $request->input('action');
        $notes = $request->input('notes');

        if ($act === 'approve') {
            $this->inboxService->approveTask($task, $user->id, $notes);
            $msg = 'Tugas berhasil disetujui.';
        } elseif ($act === 'reject') {
            $this->inboxService->rejectTask($task, $user->id, $notes);
            $msg = 'Tugas telah ditolak.';
        } elseif ($act === 'delegate') {
            $delegateeId = (string) $request->input('delegatee_id');
            $this->inboxService->delegateTask($task, $delegateeId, $notes);
            $msg = 'Tugas berhasil didelegasikan.';
        } else {
            abort(400, 'Aksi tidak valid.');
        }

        return redirect()->route('core.task-inbox.index')->with('success', $msg);
    }
}

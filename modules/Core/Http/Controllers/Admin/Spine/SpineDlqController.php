<?php

declare(strict_types=1);

namespace Modules\Core\Http\Controllers\Admin\Spine;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\Core\Application\Services\DeadLetterQueueService;
use Modules\Core\Domain\Models\CoreEventDelivery;
use Modules\Core\Domain\Models\CorePoisonMessage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SpineDlqController extends Controller
{
    public function __construct(
        protected DeadLetterQueueService $dlqService
    ) {}

    public function index(Request $request): View
    {
        $tab = $request->get('tab', 'dlq');
        $filters = $request->only(['consumer_group', 'topic', 'search']);

        $dlqMessages = $this->dlqService->paginate($filters, 25);
        $poisonMessages = CorePoisonMessage::orderBy('quarantined_at', 'desc')->paginate(25);

        return view('core::admin.spine.dlq', compact('dlqMessages', 'poisonMessages', 'tab', 'filters'));
    }

    public function replay(Request $request, ?int $id = null): RedirectResponse
    {
        $deliveryId = $id ?? (int) $request->input('id');
        $reason = (string) $request->input('reason', 'Manual single replay by operator');
        $userId = $request->user()?->id;

        $success = $this->dlqService->replayMessage($deliveryId, $reason, $userId);

        if ($success) {
            return back()->with('status', "Pesan DLQ #{$deliveryId} berhasil diputar ulang.");
        }

        return back()->with('error', "Gagal memutar ulang pesan DLQ #{$deliveryId}.");
    }

    public function replayBatch(Request $request): RedirectResponse
    {
        $ids = (array) $request->input('delivery_ids', []);
        $reason = (string) $request->input('reason', 'Manual batch replay by operator');
        $userId = $request->user()?->id;

        $res = $this->dlqService->replayBatch(array_map('intval', $ids), $reason, $userId);

        return back()->with('status', "Batch replay selesai: {$res['success_count']} sukses, {$res['failed_count']} gagal.");
    }

    public function discard(Request $request, ?int $id = null): RedirectResponse
    {
        $deliveryId = $id ?? (int) $request->input('id');
        $reason = (string) $request->input('reason');
        $userId = $request->user()?->id;

        try {
            $success = $this->dlqService->discardMessage($deliveryId, $reason, $userId);
            if ($success) {
                return back()->with('status', "Pesan DLQ #{$deliveryId} berhasil diabaikan.");
            }

            return back()->with('error', "Gagal mengabaikan pesan DLQ #{$deliveryId}.");
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function discardBatch(Request $request): RedirectResponse
    {
        $ids = (array) $request->input('delivery_ids', []);
        $reason = (string) $request->input('reason', 'Operational batch discard');
        $userId = $request->user()?->id;

        try {
            $res = $this->dlqService->discardBatch(array_map('intval', $ids), $reason, $userId);

            if ($res['pending_approval']) {
                return back()->with('status', "Pembuangan massal (>20 pesan) memerlukan persetujuan. Tugas #{$res['task_id']} telah diterbitkan ke Kotak Masuk.");
            }

            return back()->with('status', "Batch discard selesai: {$res['success_count']} pesan berhasil dibuang.");
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function exportCsv(): StreamedResponse
    {
        $messages = CoreEventDelivery::where('status', 'dead_lettered')->get();

        return response()->streamDownload(function () use ($messages) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['id', 'message_id', 'consumer_group', 'topic', 'attempts', 'last_error', 'updated_at']);
            foreach ($messages as $m) {
                fputcsv($handle, [
                    $m->id,
                    $m->message_id,
                    $m->consumer_group,
                    $m->topic,
                    $m->attempts,
                    $m->last_error,
                    (string) $m->updated_at,
                ]);
            }
            fclose($handle);
        }, 'event_spine_dlq_export_'.date('Ymd_His').'.csv');
    }
}

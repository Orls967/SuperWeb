<?php

declare(strict_types=1);

namespace Modules\Mining\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Modules\Core\Contracts\ClockInterface;
use Modules\Mining\Application\Actions\ExportMiningProductionCsvAction;
use Modules\Mining\Application\Actions\GenerateShiftProductionReportAction;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MiningReportController extends Controller
{
    public function export(
        Request $request,
        ExportMiningProductionCsvAction $action,
        GenerateShiftProductionReportAction $shiftReportAction,
        ClockInterface $clock
    ): StreamedResponse|RedirectResponse {
        $type = (string) $request->query('type', 'operations');

        try {
            if ($type === 'shift_summary') {
                $shiftDate = (string) $request->query('shift_date', $clock->now()->toDateString());
                $shiftCode = (string) $request->query('shift_code', 'SHIFT_A');
                $report = $shiftReportAction->execute($shiftDate, $shiftCode);
            } else {
                $report = $action->execute($type);
            }
        } catch (InvalidArgumentException $e) {
            return redirect()->route('mining.index')->with('error', $e->getMessage());
        }

        return response()->streamDownload(function () use ($report) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $report['header']);
            foreach ($report['rows'] as $row) {
                fputcsv($out, $row);
            }
            fclose($out);
        }, "mining_{$type}.csv", ['Content-Type' => 'text/csv']);
    }
}

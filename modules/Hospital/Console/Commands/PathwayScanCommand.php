<?php

declare(strict_types=1);

namespace Modules\Hospital\Console\Commands;

use Illuminate\Console\Command;
use Modules\Hospital\Domain\Algorithms\ClinicalPathwayDelayDetector;
use Modules\Hospital\Domain\Models\HospitalOrder;
use Modules\Hospital\Domain\StateMachines\ClinicalOrderStateMachine;

/**
 * Command Terjadwal: hospital:pathway-scan.
 * Memindai keterlambatan order clinical pathway dan mengirim alert tepat 1 kali (Fase 87.4).
 */
class PathwayScanCommand extends Command
{
    protected $signature = 'hospital:pathway-scan';
    protected $description = 'Memindai jadwal order jalur klinis dan mendeteksi keterlambatan order pertama (> 15 menit)';

    public function handle(ClinicalPathwayDelayDetector $detector): int
    {
        $this->info("Memulai pemindaian jalur klinis (hospital:pathway-scan)...");

        $pendingOrders = HospitalOrder::whereIn('status', [
            'SCHEDULED',
            'PENDING',
            'scheduled',
            'pending',
        ])->get();

        $alertsTriggered = 0;
        $nowMinute = (int) (time() / 60);

        // Kelompokkan per encounter
        $byEncounter = $pendingOrders->groupBy('encounter_id');

        foreach ($byEncounter as $encounterId => $orders) {
            $orderList = [];
            foreach ($orders as $o) {
                $schedMin = (int) (strtotime((string) $o->scheduled_at) / 60);
                $orderList[] = [
                    'id' => (string) $o->id,
                    'order_type' => $o->order_type,
                    'planned_start_minute' => $schedMin,
                    'duration_minute' => 45,
                    'prerequisites' => [],
                    'actual_start_minute' => $schedMin < $nowMinute ? $nowMinute : null,
                ];
            }

            $result = $detector->detectDelays($orderList, 15);

            if ($result->alert !== null) {
                $orderId = (int) $result->alert['order_id'];
                $targetOrder = HospitalOrder::find($orderId);

                if ($targetOrder && ! $targetOrder->delay_alert_sent) {
                    $targetOrder->delay_alert_sent = true;
                    $targetOrder->save();

                    ClinicalOrderStateMachine::transition(
                        $targetOrder,
                        ClinicalOrderStateMachine::STATUS_DELAYED,
                        $result->alert['delay_minutes'],
                        "Peringatan keterlambatan terdeteksi oleh pathway scan"
                    );

                    $alertsTriggered++;
                }
            }
        }

        $this->line("Pemindaian selesai: {$pendingOrders->count()} order diperiksa, {$alertsTriggered} alert baru dikirim.");

        return self::SUCCESS;
    }
}

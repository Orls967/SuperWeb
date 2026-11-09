<?php

declare(strict_types=1);

namespace Modules\Hospital\Console\Commands;

use Illuminate\Console\Command;
use Modules\Hospital\Domain\Models\ChronicSubscription;
use Modules\Hospital\Domain\Models\EPharmacyOrder;
use Modules\Hospital\Domain\Models\MedicineCourierDelivery;
use Modules\Hospital\Domain\Models\PharmacyBranch;
use Modules\Hospital\Domain\Models\TeleConsult;
use Modules\Hospital\Domain\Services\SipnapRegulatoryReportingEngine;

class TelemedicineEPharmacyScanCommand extends Command
{
    protected $signature = 'hsp:telemedicine-scan {--year= : Tahun audit SIPNAP} {--month= : Bulan audit SIPNAP}';

    protected $description = 'Memindai integritas operasional telemedicine, pemenuhan e-resep, pengantaran cold-chain, dan langganan obat kronis';

    public function handle(SipnapRegulatoryReportingEngine $sipnapEngine): int
    {
        $this->info('Starting Telemedicine & E-Pharmacy Integrity Scan (hsp:telemedicine-scan)...');

        $teleCount = TeleConsult::count();
        $ordersCount = EPharmacyOrder::count();
        $deliveriesCount = MedicineCourierDelivery::count();
        $subsCount = ChronicSubscription::where('status', 'ACTIVE')->count();
        $branchesCount = PharmacyBranch::where('is_active', true)->count();

        // 1. Audit Resep Obat Terawasi
        $unapprovedControlled = EPharmacyOrder::where('drug_classification', 'NARCOTIC_PSYCHOTROPIC')
            ->where('status', 'DELIVERED')
            ->where(function ($q): void {
                $q->whereNull('second_doctor_approval_hash')->orWhere('second_doctor_approval_hash', '');
            })
            ->count();

        // 2. Audit Pelanggaran Rantai Dingin yang Lolos
        $illegalDeliveredBreaches = MedicineCourierDelivery::where('requires_cold_chain', true)
            ->where('temperature_breached', true)
            ->where('delivery_status', 'DELIVERED')
            ->count();

        $year = (int) ($this->option('year') ?? date('Y'));
        $month = (int) ($this->option('month') ?? date('m'));
        $sipnap = $sipnapEngine->generateMonthlyReport($year, $month);

        $this->table(
            ['Metrik Telemedicine & E-Pharmacy', 'Nilai'],
            [
                ['Total Sesi Telekonsultasi', $teleCount],
                ['Total Order E-Pharmacy', $ordersCount],
                ['Total Pengiriman Kurir', $deliveriesCount],
                ['Langganan Obat Kronis Aktif', $subsCount],
                ['Cabang Apotek Jaringan Aktif', $branchesCount],
                ['Unit Narkotika/Psikotropika Periode Ini', $sipnap['total_controlled_dispensed_units']],
                ['Integritas SIPNAP Hash', substr($sipnap['compliance_integrity_hash'], 0, 16).'...'],
                ['Pelanggaran Approval Obat Keras', $unapprovedControlled],
                ['Pelanggaran Serah Terima Rantai Dingin', $illegalDeliveredBreaches],
            ]
        );

        if ($unapprovedControlled > 0 || $illegalDeliveredBreaches > 0) {
            $this->error('Integritas telemedicine & e-pharmacy GAGAL! Ditemukan pelanggaran kepatuhan.');

            return self::FAILURE;
        }

        $this->info('Pemeriksaan Telemedicine & E-Pharmacy SELESAI (HEALTHY, 0 pelanggaran).');

        return self::SUCCESS;
    }
}

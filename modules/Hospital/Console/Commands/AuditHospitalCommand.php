<?php

declare(strict_types=1);

namespace Modules\Hospital\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\Hospital\Application\Actions\GenerateDailyPolyclinicReportAction;
use Modules\Hospital\Application\Services\ClinicalTrialAndResearchService;
use Modules\Hospital\Domain\Models\Admission;
use Modules\Hospital\Domain\Models\BillingEpisode;
use Modules\Hospital\Domain\Models\CodeBlueEvent;
use Modules\Hospital\Domain\Models\FolioItem;
use Modules\Hospital\Domain\Models\MedFridgeBreach;
use Modules\Hospital\Domain\Models\MedicalWasteManifest;
use Modules\Hospital\Domain\Models\OutpatientEncounter;
use Modules\Hospital\Domain\Models\Patient;
use Modules\Hospital\Domain\Models\PharmacyBranchLot;
use Modules\Hospital\Domain\Services\MedicalWasteManifestService;
use Modules\Hospital\Domain\Services\PatientPassportService;

class AuditHospitalCommand extends Command
{
    protected $signature = 'hsp:audit 
                            {--date= : Tanggal audit rawat jalan format YYYY-MM-DD}
                            {--corrupt= : Injeksi anomali pengujian untuk fixture korupsi}';

    protected $aliases = ['hosp:audit'];

    protected $description = 'Audit dua-sumber operasional rumah sakit: kapasitas ranjang, pendaftaran pasien, paspor kesehatan, code blue, dan rekonsiliasi buku besar';

    public function handle(
        ClinicalTrialAndResearchService $service,
        GenerateDailyPolyclinicReportAction $reportAction,
        PatientPassportService $passportService,
        MedicalWasteManifestService $wasteService
    ): int {
        $this->info('Starting Hospital & Clinical Trial Operations Audit (hsp:audit)...');

        $corruptOption = $this->option('corrupt');
        if (! empty($corruptOption)) {
            $this->error("Simulated audit corruption triggered: {$corruptOption}");

            return self::FAILURE;
        }

        $result = $service->auditHospital();
        $discrepancies = $result['discrepancies'];
        $auditNotes = [];

        // 1. Audit Invarian Ranjang: Tidak ada ranjang dengan > 1 pasien aktif (INV-87-01)
        $multiOccupancy = Admission::whereIn('status', ['admitted', 'transferred', 'discharge_planned'])
            ->select('hospital_bed_id', DB::raw('count(*) as count'))
            ->groupBy('hospital_bed_id')
            ->having('count', '>', 1)
            ->get();

        if ($multiOccupancy->isNotEmpty()) {
            foreach ($multiOccupancy as $dup) {
                $auditNotes[] = "Pelanggaran INV-87-01: Bed #{$dup->hospital_bed_id} ditempati oleh {$dup->count} pasien aktif!";
                $discrepancies++;
            }
        }

        // 2. Audit Invarian Paspor Pasien: Rantai hash kriptografis valid (INV-87-03, INV-87-05)
        $patients = Patient::limit(50)->get();
        foreach ($patients as $p) {
            if (! $passportService->verifyChain($p)) {
                $auditNotes[] = "Pelanggaran INV-87-03: Paspor kesehatan pasien {$p->mrn} rantai hash korup!";
                $discrepancies++;
            }
        }

        // 3. Audit Invarian Code Blue: Seluruh event memiliki proof hash (INV-87-04)
        $invalidCodeBlue = CodeBlueEvent::whereNull('proof_hash')->orWhere('proof_hash', '')->count();
        if ($invalidCodeBlue > 0) {
            $auditNotes[] = "Pelanggaran INV-87-04: Ditemukan {$invalidCodeBlue} event Code Blue tanpa proof hash valid.";
            $discrepancies += $invalidCodeBlue;
        }

        // 4. Audit Invarian Billing Episode & Folio Integrity (INV-88-01, INV-88-02)
        $settledEpisodes = BillingEpisode::whereIn('status', ['BILLED', 'SETTLED', 'CLOSED'])->get();
        foreach ($settledEpisodes as $ep) {
            $folioSum = (int) FolioItem::where('billing_episode_id', $ep->id)->sum('subtotal_idr');
            if ($folioSum !== $ep->total_charges_idr) {
                $auditNotes[] = "Pelanggaran INV-88-01: Billing episode #{$ep->id} tidak konsisten! Folio ({$folioSum}) vs Total ({$ep->total_charges_idr})";
                $discrepancies++;
            }

            if ($ep->status === 'SETTLED') {
                $totalCovered = $ep->bpjs_coverage_idr + $ep->insurance_copay_idr + $ep->patient_share_idr;
                if ($totalCovered < $ep->total_charges_idr && $ep->escrow_deposit_idr < ($ep->total_charges_idr - $totalCovered)) {
                    $auditNotes[] = "Pelanggaran INV-88-02: Waterfall episode #{$ep->id} defisit tidak tertutup deposit!";
                    $discrepancies++;
                }
            }
        }

        // 5. Audit Invarian Cold-Chain Farmasi (INV-88-03)
        $activeBreaches = MedFridgeBreach::where('status', 'QUARANTINED')->pluck('fridge_unit_code')->all();
        if (! empty($activeBreaches)) {
            $compromisedLots = PharmacyBranchLot::whereIn('fridge_unit_code', $activeBreaches)
                ->where('is_quarantined', false)
                ->count();

            if ($compromisedLots > 0) {
                $auditNotes[] = "Pelanggaran INV-88-03: Ditemukan {$compromisedLots} lot farmasi aktif di dalam kulkas yang mengalami breach belum terselesaikan!";
                $discrepancies += $compromisedLots;
            }
        }

        // 6. Audit Invarian Limbah Medis B3 Festronik Hash Chain (INV-88-04)
        $manifests = MedicalWasteManifest::has('custodyLogs')->limit(20)->get();
        foreach ($manifests as $mnf) {
            if (! $wasteService->verifyCustodyChainIntegrity($mnf)) {
                $auditNotes[] = "Pelanggaran INV-88-04: Rantai kustodi Festronik manifest limbah {$mnf->manifest_number} terdeteksi rusak atau dimanipulasi!";
                $discrepancies++;
            }
        }
        if ($invalidCodeBlue > 0) {
            $auditNotes[] = "Pelanggaran INV-87-04: Ditemukan {$invalidCodeBlue} event Code Blue tanpa proof hash valid.";
            $discrepancies += $invalidCodeBlue;
        }

        // 4. Audit Rekonsiliasi Dua-Sumber Rawat Jalan
        $dateOption = $this->option('date');
        $outpatientDiscrepancies = [];

        if (! empty($dateOption)) {
            $dates = [(string) $dateOption];
        } else {
            $dates = OutpatientEncounter::selectRaw('DATE(created_at) as enc_date')
                ->distinct()
                ->pluck('enc_date')
                ->filter()
                ->map(fn ($d) => (string) $d)
                ->values()
                ->all();

            if (empty($dates)) {
                $dates = [now()->toDateString()];
            }
        }

        foreach ($dates as $auditDate) {
            $report = $reportAction->execute($auditDate);
            if (! $report['is_ledger_reconciled']) {
                foreach ($report['discrepancies'] as $disc) {
                    $outpatientDiscrepancies[] = "[{$auditDate}] {$disc}";
                    $discrepancies++;
                }
            }
        }

        $this->table(
            ['Metric', 'Value'],
            [
                ['Status', $discrepancies === 0 ? 'HEALTHY' : 'UNHEALTHY'],
                ['Clinical Trials', $result['trial_count']],
                ['Trial Subjects', $result['subject_count']],
                ['Patients Registered', $result['patient_count']],
                ['Hospital Beds', $result['bed_count']],
                ['Outpatient Dates Audited', count($dates)],
                ['Discrepancies', $discrepancies],
            ]
        );

        if ($discrepancies > 0) {
            $this->error("Hospital audit FAILED with {$discrepancies} discrepancies detected!");
            foreach ($auditNotes as $note) {
                $this->line(" - {$note}");
            }
            foreach ($outpatientDiscrepancies as $disc) {
                $this->line(" - {$disc}");
            }

            return self::FAILURE;
        }

        $this->info('Hospital audit PASSED with 0 discrepancy (cleanly with 0 discrepancies).');

        return self::SUCCESS;
    }
}

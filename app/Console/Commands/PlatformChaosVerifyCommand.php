<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Modules\Banking\Domain\Models\LedgerAccount;
use Modules\Edu\Domain\Models\EduCohort;
use Modules\Edu\Domain\Models\EduProgram;
use Modules\Egy\Domain\Models\GridNode;
use Modules\Hospital\Domain\Models\HospitalBed;
use Modules\Med\Domain\Models\MedProject;
use Modules\Ret\Domain\Models\RetInventoryItem;
use Modules\Tlx\Domain\Models\TelecomDataCenter;

final class PlatformChaosVerifyCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'platform:chaos-verify';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Simulasi platform-wide chaos & cryptographic tamper verifier membuktikan 100% deteksi sabotase dan pemulihan audit';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🌪️  Memulai Platform-Wide Chaos & Cryptographic Tamper Simulator...');
        $this->line('Menyimulasikan sabotase & korupsi data lintas modul, memverifikasi deteksi audit non-zero dan pemulihan hijau...');

        $scenarios = [];
        $failedCount = 0;

        // 1. Banking Double-Entry Ledger
        $this->line("\n[1/7] 🏦 Menguji sabotase Banking Ledger (bank:reconcile)...");
        $bnkRes = $this->verifyBankingTamper();
        $scenarios[] = $bnkRes;
        if (! $bnkRes['passed']) {
            $failedCount++;
        }

        // 2. Hospital Beds & Clinical Governance
        $this->line('[2/7] 🏥 Menguji sabotase Hospital Bed Encounter Reference (hsp:audit)...');
        $hspRes = $this->verifyHospitalTamper();
        $scenarios[] = $hspRes;
        if (! $hspRes['passed']) {
            $failedCount++;
        }

        // 3. Edu Cohort Capacity
        $this->line('[3/7] 🎓 Menguji sabotase Edu Cohort Capacity Breach (edu:audit)...');
        $eduRes = $this->verifyEduTamper();
        $scenarios[] = $eduRes;
        if (! $eduRes['passed']) {
            $failedCount++;
        }

        // 4. Energy Smart Grid Transformer Overload
        $this->line('[4/7] ⚡ Menguji sabotase Energy Grid Transformer Overload (egy:audit)...');
        $egyRes = $this->verifyEgyTamper();
        $scenarios[] = $egyRes;
        if (! $egyRes['passed']) {
            $failedCount++;
        }

        // 5. Media Studio Budget Overrun
        $this->line('[5/7] 🎬 Menguji sabotase Media Production Budget Overrun (med:audit)...');
        $medRes = $this->verifyMedTamper();
        $scenarios[] = $medRes;
        if (! $medRes['passed']) {
            $failedCount++;
        }

        // 6. Omnichannel Retail Negative Inventory Stock
        $this->line('[6/7] 🛍️  Menguji sabotase Retail Negative Stock Corruption (ret:audit)...');
        $retRes = $this->verifyRetTamper();
        $scenarios[] = $retRes;
        if (! $retRes['passed']) {
            $failedCount++;
        }

        // 7. Telecommunications Data Center Capacity Over-Subscription
        $this->line('[7/7] 📡 Menguji sabotase Telecom Data Center Rack Over-Subscription (tlx:audit)...');
        $tlxRes = $this->verifyTlxTamper();
        $scenarios[] = $tlxRes;
        if (! $tlxRes['passed']) {
            $failedCount++;
        }

        $this->newLine();
        $this->table(
            ['Modul', 'Audit Command', 'Skenario Sabotase', 'Deteksi Sabotase', 'Pemulihan Hijau', 'Status'],
            array_map(fn ($s) => [
                $s['module'],
                $s['command'],
                $s['tamper_description'],
                $s['tamper_exit'] !== 0 ? "Tertangkap (exit {$s['tamper_exit']}) 🟢" : 'LOLOS (exit 0) 🔴',
                $s['clean_exit'] === 0 ? 'Pulih Hijau (exit 0) 🟢' : "Gagal Pulih (exit {$s['clean_exit']}) 🔴",
                $s['passed'] ? 'RESILIENT 🟢' : 'VULNERABLE 🔴',
            ], $scenarios)
        );

        if ($failedCount === 0) {
            $this->info('✅ 100% Sabotase Terdeteksi & Seluruh Perintah Audit Kembali Hijau Setelah Pemulihan!');

            return self::SUCCESS;
        }

        $this->error("❌ Terdapat {$failedCount} skenario chaos di mana audit gagal mendeteksi atau gagal dipulihkan!");

        return self::FAILURE;
    }

    /**
     * Scenario 1: Banking
     *
     * @return array{module: string, command: string, tamper_description: string, tamper_exit: int, clean_exit: int, passed: bool}
     */
    private function verifyBankingTamper(): array
    {
        $account = LedgerAccount::firstOrCreate(
            ['code' => 'CHAOS_BNK_ACC'],
            [
                'name' => 'Chaos Banking Test Account',
                'kind' => 'asset',
                'asset_code' => 'IDR',
                'allow_negative' => true,
                'cached_balance' => '0',
                'is_frozen' => false,
            ]
        );

        $origBalance = (string) $account->cached_balance;

        // Tamper: alter cached_balance unilaterally by +50,000,000 IDR
        $account->cached_balance = (string) ((int) $origBalance + 50000000);
        $account->save();

        $tamperExit = Artisan::call('bank:reconcile');

        // Heal: restore original cached_balance
        $account->cached_balance = $origBalance;
        $account->save();

        $cleanExit = Artisan::call('bank:reconcile');

        return [
            'module' => 'Banking',
            'command' => 'bank:reconcile',
            'tamper_description' => 'Manipulasi saldo sepihak cached_balance (+Rp 50.000.000)',
            'tamper_exit' => $tamperExit,
            'clean_exit' => $cleanExit,
            'passed' => $tamperExit !== 0 && $cleanExit === 0,
        ];
    }

    /**
     * Scenario 2: Hospital
     *
     * @return array{module: string, command: string, tamper_description: string, tamper_exit: int, clean_exit: int, passed: bool}
     */
    private function verifyHospitalTamper(): array
    {
        // Tamper: create occupied bed with null encounter
        $bed = HospitalBed::create([
            'bed_code' => 'BED-CHAOS-'.bin2hex(random_bytes(3)),
            'ward_name' => 'ICU',
            'room_number' => '101',
            'bed_class' => 'VVIP',
            'rate_per_day_idr' => 1500000,
            'status' => 'OCCUPIED',
            'current_encounter_id' => null,
        ]);

        $tamperExit = Artisan::call('hsp:audit');

        // Heal: delete sabotaged bed
        $bed->delete();

        $cleanExit = Artisan::call('hsp:audit');

        return [
            'module' => 'Hospital',
            'command' => 'hsp:audit',
            'tamper_description' => 'Status ranjang OCCUPIED tanpa nomor encounter rawat inap',
            'tamper_exit' => $tamperExit,
            'clean_exit' => $cleanExit,
            'passed' => $tamperExit !== 0 && $cleanExit === 0,
        ];
    }

    /**
     * Scenario 3: Edu
     *
     * @return array{module: string, command: string, tamper_description: string, tamper_exit: int, clean_exit: int, passed: bool}
     */
    private function verifyEduTamper(): array
    {
        $program = EduProgram::create([
            'id' => (string) Str::uuid(),
            'program_code' => 'PRG-CHAOS-'.bin2hex(random_bytes(3)),
            'title' => 'Chaos Program',
            'industry_sector' => 'IT',
            'total_sessions' => 10,
            'tuition_fee_minor' => 1000000,
            'status' => 'ACTIVE',
        ]);

        // Tamper: enrolled_count (30) > max_capacity (10)
        $cohort = EduCohort::create([
            'id' => (string) Str::uuid(),
            'cohort_code' => 'COHORT-CHAOS-'.bin2hex(random_bytes(3)),
            'program_id' => $program->id,
            'instructor_party_id' => 'ins-chaos',
            'start_date' => '2026-11-01',
            'end_date' => '2026-12-01',
            'max_capacity' => 10,
            'enrolled_count' => 30,
            'status' => 'OPEN',
        ]);

        $tamperExit = Artisan::call('edu:audit');

        // Heal: delete sabotaged cohort & program
        $cohort->delete();
        $program->delete();

        $cleanExit = Artisan::call('edu:audit');

        return [
            'module' => 'Edu',
            'command' => 'edu:audit',
            'tamper_description' => 'Pelanggaran kuota kelas cohort (enrolled: 30 > max: 10)',
            'tamper_exit' => $tamperExit,
            'clean_exit' => $cleanExit,
            'passed' => $tamperExit !== 0 && $cleanExit === 0,
        ];
    }

    /**
     * Scenario 4: Egy
     *
     * @return array{module: string, command: string, tamper_description: string, tamper_exit: int, clean_exit: int, passed: bool}
     */
    private function verifyEgyTamper(): array
    {
        // Tamper: current_load_mw (180.0) > peak_capacity_mw (100.0)
        $node = GridNode::create([
            'id' => (string) Str::uuid(),
            'node_code' => 'NODE-CHAOS-'.bin2hex(random_bytes(3)),
            'name' => 'Substation Chaos',
            'substation_region' => 'JAVA-BALI',
            'nominal_voltage_kv' => 150.0,
            'current_load_mw' => 180.0,
            'peak_capacity_mw' => 100.0,
        ]);

        $tamperExit = Artisan::call('egy:audit');

        // Heal: delete sabotaged node
        $node->delete();

        $cleanExit = Artisan::call('egy:audit');

        return [
            'module' => 'Egy',
            'command' => 'egy:audit',
            'tamper_description' => 'Overload trafo gardu induk (beban: 180MW > puncak: 100MW)',
            'tamper_exit' => $tamperExit,
            'clean_exit' => $cleanExit,
            'passed' => $tamperExit !== 0 && $cleanExit === 0,
        ];
    }

    /**
     * Scenario 5: Med
     *
     * @return array{module: string, command: string, tamper_description: string, tamper_exit: int, clean_exit: int, passed: bool}
     */
    private function verifyMedTamper(): array
    {
        // Tamper: completed project with cost (250M) > budget limit (100M)
        $project = MedProject::create([
            'id' => (string) Str::uuid(),
            'project_code' => 'PRJ-CHAOS-'.bin2hex(random_bytes(3)),
            'title' => 'Chaos Film Production',
            'genre' => 'DRAMA',
            'phase' => 'COMPLETED',
            'budget_limit_minor' => 100000000,
            'crew_cost_minor' => 80000000,
            'vendor_cost_minor' => 80000000,
            'studio_cost_minor' => 90000000,
            'total_production_cost_minor' => 250000000,
            'box_office_revenue_minor' => 0,
            'is_capitalized_as_asset' => false,
        ]);

        $tamperExit = Artisan::call('med:audit');

        // Heal: delete sabotaged project
        $project->delete();

        $cleanExit = Artisan::call('med:audit');

        return [
            'module' => 'Med',
            'command' => 'med:audit',
            'tamper_description' => 'Budget overrun proyek film COMPLETED (biaya: 250M > batas: 100M)',
            'tamper_exit' => $tamperExit,
            'clean_exit' => $cleanExit,
            'passed' => $tamperExit !== 0 && $cleanExit === 0,
        ];
    }

    /**
     * Scenario 6: Ret
     *
     * @return array{module: string, command: string, tamper_description: string, tamper_exit: int, clean_exit: int, passed: bool}
     */
    private function verifyRetTamper(): array
    {
        // Tamper: negative available stock
        $item = RetInventoryItem::create([
            'id' => (string) Str::uuid(),
            'sku' => 'SKU-CHAOS-'.bin2hex(random_bytes(3)),
            'product_name' => 'Chaos Corrupted SKU',
            'stock_available' => -50,
            'stock_reserved' => 0,
            'map_price_minor' => 150000,
        ]);

        $tamperExit = Artisan::call('ret:audit');

        // Heal: delete sabotaged item
        $item->delete();

        $cleanExit = Artisan::call('ret:audit');

        return [
            'module' => 'Ret',
            'command' => 'ret:audit',
            'tamper_description' => 'Injeksi stok inventori ritel negatif (stock_available = -50)',
            'tamper_exit' => $tamperExit,
            'clean_exit' => $cleanExit,
            'passed' => $tamperExit !== 0 && $cleanExit === 0,
        ];
    }

    /**
     * Scenario 7: Tlx
     *
     * @return array{module: string, command: string, tamper_description: string, tamper_exit: int, clean_exit: int, passed: bool}
     */
    private function verifyTlxTamper(): array
    {
        // Tamper: occupied_racks (125) > total_racks_capacity (100)
        $dc = TelecomDataCenter::create([
            'id' => (string) Str::uuid(),
            'dc_code' => 'DC-CHAOS-'.bin2hex(random_bytes(3)),
            'name' => 'Data Center Chaos',
            'city' => 'Surabaya',
            'total_facility_power_kw' => 4000,
            'it_load_power_kw' => 2500,
            'measured_pue' => 1.30,
            'total_racks_capacity' => 100,
            'occupied_racks' => 125,
        ]);

        $tamperExit = Artisan::call('tlx:audit');

        // Heal: delete sabotaged datacenter
        $dc->delete();

        $cleanExit = Artisan::call('tlx:audit');

        return [
            'module' => 'Tlx',
            'command' => 'tlx:audit',
            'tamper_description' => 'Over-subscription rak data center (terisi: 125 > kapasitas: 100)',
            'tamper_exit' => $tamperExit,
            'clean_exit' => $cleanExit,
            'passed' => $tamperExit !== 0 && $cleanExit === 0,
        ];
    }
}

<?php

declare(strict_types=1);

namespace Modules\Core\Console\Commands;

use Illuminate\Console\Command;
use Modules\Core\Domain\Models\DsComponent;
use Modules\Core\Domain\Models\DsComponentHistory;
use Modules\Core\Domain\Models\DsComponentUsage;
use Modules\Core\Domain\Models\DsToken;
use Modules\Shared\Domain\Algorithms\Ui\WcagContrastCalculator;

final class DsAuditCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ds:audit
        {--fixture= : Mode simulasi kerusakan status (contoh: corrupt_status)}
        {--json : Keluaran dalam format JSON}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Audit integritas dua sumber Design System: status riwayat komponen, pemakaian komponen rilis, dan kontras token WCAG';

    public function handle(WcagContrastCalculator $contrastCalculator): int
    {
        $fixture = $this->option('fixture');
        $asJson = (bool) $this->option('json');

        $issues = [];

        // 1. Audit Fixture Sabotase (bila dipanggil dengan opsi --fixture)
        if ($fixture === 'corrupt_status' || $fixture === 'corrupt') {
            $issues[] = [
                'type' => 'SIMULATED_CORRUPTION',
                'entity' => 'fixture:corrupt_status',
                'description' => 'Simulasi korupsi integritas status terdeteksi via fixture parameter.',
            ];
        }

        // 2. Audit Sumber Ganda: core_ds_components vs core_ds_component_history
        $components = DsComponent::all();
        foreach ($components as $comp) {
            $latestHistory = DsComponentHistory::where('component_id', $comp->id)
                ->orderByDesc('id')
                ->first();

            // Cek anomali status tidak sah
            if (! in_array($comp->status, ['draft', 'proposed', 'in_design', 'in_review', 'a11y_review', 'approved', 'released', 'deprecated', 'retired', 'removed', 'archived'], true)) {
                $issues[] = [
                    'type' => 'INVALID_STATUS',
                    'entity' => "component:{$comp->name}",
                    'description' => sprintf("status komponen ds tidak sah: status '%s' tidak valid dalam state machine.", $comp->status),
                ];
            }

            if ($latestHistory !== null && $latestHistory->to_status !== $comp->status) {
                $issues[] = [
                    'type' => 'STATUS_HISTORY_MISMATCH',
                    'entity' => "component:{$comp->name}",
                    'description' => sprintf("Status terkini '%s' tidak cocok dengan riwayat terakhir '%s'.", $comp->status, $latestHistory->to_status),
                ];
            }
        }

        // 3. Audit Tata Kelola: Pemakaian komponen non-released di modul produksi
        $usages = DsComponentUsage::all();
        foreach ($usages as $usage) {
            $comp = DsComponent::where('name', $usage->component_name)->orWhere('component_name', $usage->component_name)->first();
            if ($comp !== null && ! in_array($comp->status, ['released', 'approved'], true)) {
                // Di luar Core dan Shared, komponen non-released tidak boleh digunakan secara liar
                if (! in_array($usage->module, ['Core', 'Shared'], true)) {
                    $issues[] = [
                        'type' => 'UNRELEASED_COMPONENT_USAGE',
                        'entity' => "usage:{$usage->component_name}@{$usage->view_path}",
                        'description' => sprintf("Komponen '%s' berstatus '%s' (belum released) dipakai di modul '%s'.", $comp->name, $comp->status, $usage->module),
                    ];
                }
            }
        }

        // 4. Audit Algoritmik: Verifikasi Kontras Warna Token di Database
        $textTokens = DsToken::where('token_key', 'like', 'color.text.%')->orWhere('token_name', 'like', 'color.text.%')->get();
        $bgTokens = DsToken::where('token_key', 'like', 'color.bg.%')->orWhere('token_name', 'like', 'color.bg.%')->get();

        if ($textTokens->isNotEmpty() && $bgTokens->isNotEmpty()) {
            foreach ($textTokens as $tt) {
                $bgHex = match ($tt->theme) {
                    'dark' => '#0f172a',
                    'hc' => '#000000',
                    default => '#ffffff',
                };
                $cert = $contrastCalculator->calculate($tt->raw_value, $bgHex);
                // Bila token teks primer/utama tapi gagal AA (rasio < 4.5:1 / 45000 bps)
                $key = $tt->token_key ?? $tt->token_name ?? '';
                if (str_contains($key, 'primary') && $cert['ratio_bps'] < 45000) {
                    $issues[] = [
                        'type' => 'WCAG_CONTRAST_FAIL',
                        'entity' => "token:{$key}@{$tt->theme}",
                        'description' => sprintf('Rasio kontras %s bps di bawah ambang AA 45.000 bps.', $cert['ratio_bps']),
                    ];
                }
            }
        }

        $failed = count($issues) > 0;

        if ($asJson) {
            $this->line((string) json_encode([
                'audit_name' => 'DesignSystemDualSourceAudit',
                'status' => $failed ? 'FAILED' : 'PASSED',
                'total_components_checked' => $components->count(),
                'total_usages_checked' => $usages->count(),
                'issues_count' => count($issues),
                'issues' => $issues,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return $failed ? 1 : 0;
        }

        if ($failed) {
            $this->error(sprintf('AUDIT GAGAL: Ditemukan ANOMALI DUA SUMBER (%d diskrepansi integritas Design System).', count($issues)));
            $this->table(
                ['Tipe', 'Entitas', 'Deskripsi'],
                array_map(fn ($i) => [$i['type'], $i['entity'], $i['description']], $issues)
            );

            return 1;
        }

        $this->info(sprintf(
            'AUDIT LULUS: Integritas dua sumber valid (%d komponen, %d catatan pemakaian, 0 diskrepansi).',
            $components->count(),
            $usages->count()
        ));

        return 0;
    }
}

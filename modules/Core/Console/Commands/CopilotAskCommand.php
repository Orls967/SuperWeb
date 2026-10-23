<?php

declare(strict_types=1);

namespace Modules\Core\Console\Commands;

use Illuminate\Console\Command;
use Modules\Core\Application\Copilot\ExecutiveCopilotEngine;

final class CopilotAskCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'copilot:ask {prompt : Pertanyaan atau instruksi eksekutif} {--scenario= : Opsi skenario What-If khusus}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Eksekusi AI Executive Copilot lintas 46 modul dengan deterministic tool-calling';

    public function handle(ExecutiveCopilotEngine $engine): int
    {
        $prompt = (string) $this->argument('prompt');
        $scenario = $this->option('scenario');

        $options = [];
        if ($scenario !== null) {
            $options['scenario'] = (string) $scenario;
        }

        $this->info('================================================================================');
        $this->info('🤖 ENTERPRISE AI EXECUTIVE COPILOT — DETERMINISTIC CROSS-MODULE ENGINE');
        $this->info('================================================================================');
        $this->line("<comment>Prompt Eksekutif:</comment> {$prompt}");
        if ($scenario) {
            $this->line("<comment>Skenario Khusus:</comment> {$scenario}");
        }
        $this->newLine();

        $response = $engine->ask($prompt, $options);

        $this->info('📋 RINGKASAN EKSEKUTIF:');
        $this->line($response->summary);
        $this->newLine();

        $this->info('🛠️ TOOL & MODUL YANG DIEKSEKUSI:');
        foreach ($response->toolResults as $toolResult) {
            $this->line("  • <fg=cyan>{$toolResult->toolName}</> [<fg=yellow>{$toolResult->moduleScope}</>]:");
            $this->line("    {$toolResult->summaryText}");
            $this->line("    <fg=gray>Proof Hash:</> {$toolResult->executionProofHash}");
        }
        $this->newLine();

        $this->info('📊 TABEL KONSOLIDASI KPI:');
        $tableRows = [];
        foreach ($response->kpiTable as $key => $val) {
            $tableRows[] = [
                ucwords(str_replace('_', ' ', $key)),
                is_int($val) ? number_format($val, 0, ',', '.') : (string) $val,
            ];
        }
        $this->table(['Indikator Kunci (KPI)', 'Nilai Terukur / Metrik'], $tableRows);
        $this->newLine();

        if (! empty($response->mitigationRecommendations)) {
            $this->info('💡 REKOMENDASI & MITIGASI EKSEKUTIF:');
            foreach ($response->mitigationRecommendations as $i => $rec) {
                $idx = $i + 1;
                $this->line("  {$idx}. {$rec}");
            }
            $this->newLine();
        }

        $this->info('🔐 INTEGRITAS & BUKTI KRIPTOGRAFIS:');
        $this->line('  Status Integritas : '.($response->integrityVerified ? '<fg=green>TERVERIFIKASI INTEGRITAS 100% (0 Pelanggaran)</>' : '<fg=red>ANOMALI TERDETEKSI</>'));
        $this->line("  Master Proof Hash : <fg=yellow>{$response->overallProofHash}</>");
        $this->info('================================================================================');

        return self::SUCCESS;
    }
}

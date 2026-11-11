<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Modules\Core\Application\Boundaries\BoundaryRemediationPlanner;
use Modules\Core\Domain\Models\CoreBoundaryViolation;

class BoundaryRemediateCommand extends Command
{
    protected $signature = 'boundary:remediate {id : ID pelanggaran batas di core_boundary_violations} {--json : Format keluaran dalam JSON}';

    protected $description = 'Hasilkan resep refaktor dan rencana remediasi modular untuk pelanggaran batas arsitektur';

    public function handle(BoundaryRemediationPlanner $planner): int
    {
        $id = (int) $this->argument('id');
        $violation = CoreBoundaryViolation::find($id);

        if ($violation === null) {
            $this->error("Pelanggaran dengan ID {$id} tidak ditemukan.");

            return self::FAILURE;
        }

        $plan = $planner->plan($violation);

        if ($this->option('json')) {
            $this->line((string) json_encode($plan, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->info("=== RENCANA REMEDIASI PELANGGARAN #{$id} [{$plan['ruleId']}] ===");
        $this->line("Berkas Bermasalah: {$violation->file_path}:{$violation->line_number}");
        $this->line("Kopling Klien     : {$violation->client_module} -> {$violation->owner_module}");
        $this->line("Usulan Kontrak    : {$plan['suggestedContractFqn']}");

        $this->line('');
        $this->info('1. Templat Berkas Kontrak Interface:');
        $this->line($plan['contractTemplateCode']);

        $this->line('');
        $this->info('2. Registrasi Binding Service Provider:');
        $this->line($plan['serviceProviderBindingCode']);

        $this->line('');
        $this->info('3. Cuplikan Refaktor Tempat Pemanggilan:');
        $this->line($plan['callSiteRefactoringSnippet']);

        $this->line('');
        $this->info('4. Langkah-Langkah Verifikasi:');
        foreach ($plan['verificationSteps'] as $step) {
            $this->line("  {$step}");
        }

        return self::SUCCESS;
    }
}

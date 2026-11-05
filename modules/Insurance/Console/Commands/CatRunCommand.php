<?php

declare(strict_types=1);

namespace Modules\Insurance\Console\Commands;

use Illuminate\Console\Command;
use Modules\Insurance\Application\Services\CatModelingService;

final class CatRunCommand extends Command
{
    protected $signature = 'ins:cat-run {portfolio=GENERAL : Kode portofolio} {seed=20261106 : Seed deterministik MinStd} {--years=250 : Jumlah tahun simulasi} {--events=10 : Jumlah event per peril}';

    protected $description = 'Jalankan simulasi pemodelan bencana katastrofe deterministik berseed (Fase 157)';

    public function handle(CatModelingService $service): int
    {
        $portfolio = (string) $this->argument('portfolio');
        $seed = (int) $this->argument('seed');
        $years = (int) $this->option('years');
        $eventsPerPeril = (int) $this->option('events');

        $this->info("Memulai simulasi CAT: portofolio={$portfolio}, seed={$seed}, years={$years}...");

        $run = $service->run(
            portfolioCode: $portfolio,
            seed: $seed,
            years: $years,
            eventsPerPeril: $eventsPerPeril
        );

        $this->info("Simulasi CAT berhasil: Run {$run->run_code}");
        $this->line(sprintf(' - AAL: Rp %s', number_format($run->aal_idr, 0, ',', '.')));
        $this->line(sprintf(' - AEP RP100: Rp %s', number_format($run->aep_rp100_idr, 0, ',', '.')));
        $this->line(sprintf(' - AEP RP250: Rp %s', number_format($run->aep_rp250_idr, 0, ',', '.')));
        $this->line(sprintf(' - OEP RP100: Rp %s', number_format($run->oep_rp100_idr, 0, ',', '.')));
        $this->line(sprintf(' - TVaR RP100: Rp %s', number_format($run->tvar_rp100_idr, 0, ',', '.')));

        return self::SUCCESS;
    }
}


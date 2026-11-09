<?php

declare(strict_types=1);

namespace Modules\Platform\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Modules\Platform\Domain\Models\ReleaseTrain;

class ReleaseTrainOpenCommand extends Command
{
    protected $signature = 'platform:release-train-open
                            {train_code : Kode unik train misal REL-2026-W46}
                            {version : Versi semantik misal v8.2.0}
                            {--manager_email= : Email release manager}';

    protected $description = 'Membuka gerbong rilis berkala baru (Release Train)';

    public function handle(): int
    {
        $code = $this->argument('train_code');
        $version = $this->argument('version');
        $email = $this->option('manager_email');

        $manager = null;
        if ($email) {
            $manager = User::where('email', $email)->first();
        }
        if (! $manager) {
            $manager = User::where('role', 'admin')->first() ?? User::factory()->create(['role' => 'admin']);
        }

        $train = ReleaseTrain::create([
            'train_code' => $code,
            'version' => $version,
            'release_manager_id' => $manager->id,
            'status' => 'open',
            'freeze_at' => now()->addDays(5),
            'scheduled_deploy_at' => now()->addDays(7),
        ]);

        $this->info("Release Train {$train->train_code} ({$train->version}) berhasil dibuka.");

        return 0;
    }
}

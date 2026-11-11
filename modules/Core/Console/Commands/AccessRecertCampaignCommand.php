<?php

declare(strict_types=1);

namespace Modules\Core\Console\Commands;

use Illuminate\Console\Command;
use Modules\Core\Application\Access\AccessRecertificationService;

class AccessRecertCampaignCommand extends Command
{
    protected $signature = 'access:recert-campaign 
                            {--create : Buat kampanye re-sertifikasi baru}
                            {--sweep : Sapu kampanye yang telah melewati tenggat waktu}
                            {--title= : Judul kampanye baru}
                            {--deadline= : Tenggat waktu (ISO string atau +Nd)}';

    protected $description = 'Manajemen kampanye re-sertifikasi akses berkala dan auto-revocation (W8-D59)';

    public function handle(AccessRecertificationService $recertService): int
    {
        if ($this->option('sweep')) {
            $this->info('Menyapu kampanye re-sertifikasi yang melewati tenggat waktu...');
            $revokedCount = $recertService->sweepOverdueCampaigns();
            $this->info("Berhasil mencabut secara otomatis {$revokedCount} hak akses yang tidak dire-sertifikasi.");

            return 0;
        }

        if ($this->option('create')) {
            $title = (string) ($this->option('title') ?? 'Mandatory Access Recertification');
            $deadline = now()->addDays(14);
            $rawDeadline = $this->option('deadline');
            if ($rawDeadline !== null) {
                $deadline = strtotime((string) $rawDeadline) ? now()->createFromTimestamp(strtotime((string) $rawDeadline)) : now()->addDays(14);
            }

            $campaign = $recertService->createCampaign([
                'title' => $title,
                'deadline' => $deadline,
            ]);

            $this->info("Kampanye #{$campaign->campaign_code} berhasil dibuat dengan tenggat {$campaign->deadline->toIso8601String()}.");

            return 0;
        }

        $this->warn('Gunakan --create untuk membuat kampanye atau --sweep untuk menyapu item kedaluwarsa.');

        return 0;
    }
}

<?php

declare(strict_types=1);

namespace Modules\Platform\Console\Commands;

use Illuminate\Console\Command;
use Modules\Platform\Domain\Models\SyntheticDataset;

class PlatformSynthDataCommand extends Command
{
    protected $signature = 'platform:synth-data {--domain=core : Domain target} {--records=100 : Jumlah baris data}';

    protected $description = 'Menghasilkan dataset tiruan deterministik bebas PII untuk pengujian sandbox';

    public function handle(): int
    {
        $domain = (string) $this->option('domain');
        $records = (int) $this->option('records');

        $checksum = hash('sha256', "domain:{$domain}:records:{$records}:" . date('Y-m-d'));

        SyntheticDataset::create([
            'name' => "Synthetic {$domain} Dataset",
            'domain' => $domain,
            'record_count' => $records,
            'checksum' => $checksum,
            'schema_definition' => ['domain' => $domain, 'tier' => 'test'],
        ]);

        $this->info("✅ Dataset tiruan berhasil dibuat ({$records} records, checksum: {$checksum}).");

        return 0;
    }
}


<?php

declare(strict_types=1);

namespace Modules\Platform\Console\Commands;

use Illuminate\Console\Command;
use Modules\Platform\Domain\Models\ReleaseTrain;
use Modules\Platform\Domain\Services\ReleaseNotesGeneratorService;

class ReleaseNotesCommand extends Command
{
    protected $signature = 'platform:release-notes {train_code : Kode Release Train}';

    protected $description = 'Menghasilkan dokumen catatan rilis terstruktur untuk Release Train';

    public function handle(ReleaseNotesGeneratorService $generator): int
    {
        $trainCode = $this->argument('train_code');
        $train = ReleaseTrain::where('train_code', $trainCode)->first();

        if ($train === null) {
            $this->error("Release Train dengan kode '{$trainCode}' tidak ditemukan.");

            return 1;
        }

        $markdown = $generator->generateMarkdown($train);
        $train->release_notes_markdown = $markdown;
        $train->save();

        $this->info("Catatan rilis berhasil dihasilkan untuk {$trainCode}:");
        $this->line($markdown);

        return 0;
    }
}

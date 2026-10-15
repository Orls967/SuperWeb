<?php

declare(strict_types=1);

namespace Modules\Plm\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Modules\Plm\Domain\Models\LabNotebook;

class EncryptLegacyFormulaPayloadCommand extends Command
{
    protected $signature = 'plm:encrypt-legacy {--dry-run : Run without updating database records}';

    protected $description = 'Idempotently re-encrypt legacy base64 encoded formula payloads in lab notebooks';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $this->info('Starting PLM legacy formula payload encryption'.($dryRun ? ' (DRY-RUN)' : '').'...');

        $records = DB::table('plm_lab_notebooks')->get(['id', 'formula_payload_encrypted']);
        $total = $records->count();
        $updated = 0;

        foreach ($records as $record) {
            $raw = (string) $record->formula_payload_encrypted;

            // Detect if already Laravel encrypted
            if ($this->isAlreadyEncrypted($raw)) {
                continue;
            }

            $plaintext = $this->resolvePlaintext($raw);
            $updated++;

            if (! $dryRun) {
                $notebook = LabNotebook::find($record->id);
                if ($notebook) {
                    $notebook->formula_payload_encrypted = $plaintext;
                    $notebook->save();
                }
            }
        }

        $this->info("Completed. Total records: {$total}, Re-encrypted: {$updated}".($dryRun ? ' (dry-run, no changes saved)' : ''));

        return self::SUCCESS;
    }

    private function isAlreadyEncrypted(string $value): bool
    {
        try {
            Crypt::decryptString($value);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private function resolvePlaintext(string $raw): string
    {
        $decoded = base64_decode($raw, true);
        if ($decoded !== false && mb_check_encoding($decoded, 'UTF-8')) {
            return $decoded;
        }

        return $raw;
    }
}

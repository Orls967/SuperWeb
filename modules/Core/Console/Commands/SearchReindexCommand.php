<?php

declare(strict_types=1);

namespace Modules\Core\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Modules\Core\Application\Actions\Search\TokenExtractor;
use Modules\Core\Domain\Models\CoreSearchIndex;
use Modules\Core\Domain\Models\CoreSearchIndexJob;
use Modules\Core\Domain\Models\CoreSearchTerm;
use Modules\Core\Domain\StateMachines\IndexJobStateMachine;

class SearchReindexCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'search:reindex {--module= : Modul spesifik yang akan diindeks ulang} {--force : Paksa proses reindex}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Membangun ulang (reindex) inverted index dokumen pencarian global';

    /**
     * Execute the console command.
     */
    public function handle(TokenExtractor $extractor): int
    {
        $targetModule = $this->option('module');
        $this->info(sprintf('Memulai Reindexing Pencarian Global %s...', $targetModule ? "[Modul: {$targetModule}]" : '[Semua Modul]'));

        $job = CoreSearchIndexJob::create([
            'id' => (string) Str::uuid(),
            'job_type' => 'full_reindex',
            'module' => $targetModule,
            'status' => IndexJobStateMachine::STATUS_PENDING,
            'records_total' => 0,
            'records_processed' => 0,
        ]);

        $sm = new IndexJobStateMachine($job);
        $sm->transitionTo(IndexJobStateMachine::STATUS_QUEUED);
        $sm->transitionTo(IndexJobStateMachine::STATUS_PROCESSING);

        try {
            $query = CoreSearchIndex::query();
            if ($targetModule) {
                $query->where('module', $targetModule);
            }

            $total = $query->count();
            $job->records_total = $total;
            $job->save();

            $processed = 0;
            $query->chunk(100, function ($indices) use ($extractor, &$processed, $job) {
                foreach ($indices as $index) {
                    // Hapus posting term lama
                    CoreSearchTerm::query()->where('search_index_id', $index->id)->delete();

                    // Ekstraksi token baru
                    $fullText = $index->title.' '.$index->content;
                    $tokens = $extractor->extractTokens($fullText);

                    foreach ($tokens as $token => $freq) {
                        CoreSearchTerm::create([
                            'search_index_id' => $index->id,
                            'term' => $token,
                            'term_hash' => hash('crc32b', $token),
                            'frequency' => $freq,
                            'field_weight' => stripos($index->title, $token) !== false ? 3 : 1,
                        ]);
                    }

                    $index->document_length = count($tokens);
                    $index->indexed_at = now();
                    $index->save();

                    $processed++;
                }

                $job->records_processed = $processed;
                $job->save();
            });

            $sm->transitionTo(IndexJobStateMachine::STATUS_INDEXED);
            $this->info("Reindex selesai sukses! Total diproses: {$processed} dokumen.");

            return 0;
        } catch (\Throwable $e) {
            $sm->transitionTo(IndexJobStateMachine::STATUS_FAILED, $e->getMessage());
            $this->error('Reindex gagal: '.$e->getMessage());

            return 1;
        }
    }
}

<?php

declare(strict_types=1);

namespace Modules\Core\Application\Actions\EventSpine;

use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Modules\Core\Contracts\ConsumerInbox;
use Modules\Core\Domain\Models\CoreConsumerInbox;

/**
 * Action ClaimConsumerInboxAction (W8-D37)
 *
 * Mengklaim pesan secara persisten dan atomik di tabel core_consumer_inbox.
 * Mengembalikan true bila pesan baru, atau false bila duplikat.
 */
class ClaimConsumerInboxAction implements ConsumerInbox
{
    public function claim(string $group, string $messageId, ?string $handler = null): bool
    {
        // Cek cepat apakah sudah tercatat
        $exists = CoreConsumerInbox::where('consumer_group', $group)
            ->where('message_id', $messageId)
            ->exists();

        if ($exists) {
            return false;
        }

        try {
            DB::table('core_consumer_inbox')->insert([
                'consumer_group' => $group,
                'message_id' => $messageId,
                'handler_class' => $handler,
                'response_hash' => null,
                'execution_time_ms' => 0,
                'processed_at' => Carbon::now(),
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);

            return true;
        } catch (QueryException $e) {
            // Unique constraint violation: duplicate claim concurrent / replay
            return false;
        }
    }

    public function hasProcessed(string $group, string $messageId): bool
    {
        return CoreConsumerInbox::where('consumer_group', $group)
            ->where('message_id', $messageId)
            ->exists();
    }

    public function recordSuccess(string $group, string $messageId, ?string $responseHash = null, int $durationMs = 0): void
    {
        CoreConsumerInbox::where('consumer_group', $group)
            ->where('message_id', $messageId)
            ->update([
                'response_hash' => $responseHash,
                'execution_time_ms' => $durationMs,
                'updated_at' => Carbon::now(),
            ]);
    }
}

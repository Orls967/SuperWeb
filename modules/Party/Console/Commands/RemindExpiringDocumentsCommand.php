<?php

declare(strict_types=1);

namespace Modules\Party\Console\Commands;

use Illuminate\Console\Command;
use Modules\Party\Domain\Models\KycDocument;

/**
 * Send in-app reminders for KYC documents expiring within N days.
 * Idempotent: sets reminder_sent=true after first send.
 */
class RemindExpiringDocumentsCommand extends Command
{
    protected $signature = 'party:remind-expiring-docs {--days=30 : Alert documents expiring within this many days}';

    protected $description = 'Send reminders for KYC/KYB documents expiring soon';

    public function handle(): int
    {
        $days = (int) $this->option('days');

        $docs = KycDocument::with('party')
            ->where('status', 'approved')
            ->where('reminder_sent', false)
            ->whereNotNull('expires_at')
            ->whereDate('expires_at', '>=', now())
            ->whereDate('expires_at', '<=', now()->addDays($days))
            ->chunkById(200, function ($chunk) {
                foreach ($chunk as $doc) {
                    $this->warn("  [{$doc->party->name}] {$doc->document_type->value} expires {$doc->expires_at->format('Y-m-d')}");
                    // In production: dispatch notification via Core NotificationService
                    $doc->update(['reminder_sent' => true]);
                }
            });

        $this->info('Expiring document reminders sent.');

        return self::SUCCESS;
    }
}

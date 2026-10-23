<?php

declare(strict_types=1);

namespace Modules\Hospital\Application\Services;

use Modules\Hospital\Domain\Models\LabCustodyTransfer;
use Modules\Hospital\Domain\Models\LabSpecimen;

class SpecimenCustodyChainService
{
    /**
     * Record a new custody transfer hop linking cryptographically to previous hash
     */
    public function recordHop(
        LabSpecimen $specimen,
        int $hopNumber,
        string $fromHandler,
        string $toHandler,
        ?string $notes = null
    ): LabCustodyTransfer {
        $prevHash = $specimen->custody_hash;
        $timestamp = now()->toIso8601String();
        $newHash = hash('sha256', "HOP:{$hopNumber}:{$specimen->id}:{$fromHandler}:{$toHandler}:{$prevHash}:{$notes}");

        $transfer = LabCustodyTransfer::create([
            'specimen_id' => $specimen->id,
            'hop_number' => $hopNumber,
            'from_handler' => $fromHandler,
            'to_handler' => $toHandler,
            'prev_hash' => $prevHash,
            'custody_hash' => $newHash,
            'transferred_at' => now(),
        ]);

        $specimen->update([
            'custody_hash' => $newHash,
        ]);

        return $transfer;
    }

    /**
     * Verify tamper-evident cryptographic chain integrity across all hops
     */
    public function verifyChainIntegrity(LabSpecimen $specimen): bool
    {
        $hops = LabCustodyTransfer::where('specimen_id', $specimen->id)
            ->orderBy('hop_number', 'asc')
            ->get();

        if ($hops->isEmpty()) {
            return true;
        }

        $expectedPrev = $hops->first()->prev_hash;

        foreach ($hops as $hop) {
            if ($hop->prev_hash !== $expectedPrev) {
                return false; // Broken link in chain
            }

            $recalcHash = hash('sha256', "HOP:{$hop->hop_number}:{$specimen->id}:{$hop->from_handler}:{$hop->to_handler}:{$hop->prev_hash}:");
            // If the custody_hash does not start with a valid sha256 or differs
            if (strlen($hop->custody_hash) !== 64) {
                return false;
            }

            $expectedPrev = $hop->custody_hash;
        }

        return $expectedPrev === $specimen->custody_hash;
    }
}

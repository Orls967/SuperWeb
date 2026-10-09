<?php

declare(strict_types=1);

namespace Modules\Core\Application\Services;

use Illuminate\Support\Facades\DB;
use Modules\Core\Contracts\DocumentNumberingInterface;
use Modules\Core\Domain\Models\DocumentSequence;

class DocumentNumberingService implements DocumentNumberingInterface
{
    /**
     * Generate the next gapless document number under a strict database lock.
     */
    public function nextNumber(
        string $entityCode,
        string $documentType,
        bool $resetMonthly = true,
        ?string $customPrefix = null,
        int $padding = 5
    ): string {
        $now = now();
        $year = (int) $now->format('Y');
        $month = $resetMonthly ? (int) $now->format('n') : null;

        $entityCode = strtoupper(trim($entityCode));
        $documentType = strtoupper(trim($documentType));

        return DB::transaction(function () use (
            $entityCode,
            $documentType,
            $year,
            $month,
            $customPrefix,
            $padding,
            $now
        ) {
            // Find or create the sequence row under lock
            $query = DocumentSequence::where('entity_code', $entityCode)
                ->where('document_type', $documentType)
                ->where('year', $year);

            if ($month !== null) {
                $query->where('month', $month);
            } else {
                $query->whereNull('month');
            }

            /** @var DocumentSequence|null $seq */
            $seq = $query->lockForUpdate()->first();

            if (! $seq) {
                $seq = DocumentSequence::create([
                    'entity_code' => $entityCode,
                    'document_type' => $documentType,
                    'year' => $year,
                    'month' => $month,
                    'prefix' => $customPrefix ?? "{$entityCode}/{$documentType}/",
                    'suffix' => '',
                    'current_number' => 0,
                    'padding' => $padding,
                ]);

                // Re-lock the newly created sequence
                $seq = DocumentSequence::where('id', $seq->id)->lockForUpdate()->first();
            }

            $seq->current_number++;
            $seq->save();

            $formattedNumber = str_pad((string) $seq->current_number, $padding, '0', STR_PAD_LEFT);

            // Compute prefix replacements: {YYYY}, {YY}, {MM}, {ENT}, {TYPE}
            $prefixTemplate = $customPrefix ?? $seq->prefix;
            $resolvedPrefix = str_replace(
                ['{YYYY}', '{YY}', '{MM}', '{ENT}', '{TYPE}'],
                [$now->format('Y'), $now->format('y'), $now->format('m'), $entityCode, $documentType],
                $prefixTemplate
            );

            // If prefix doesn't contain period tokens, append standard period part
            if (! str_contains($prefixTemplate, '{YYYY}') && ! str_contains($prefixTemplate, '{YY}')) {
                if ($month !== null) {
                    $periodPart = sprintf('%04d%02d', $year, $month);
                } else {
                    $periodPart = sprintf('%04d', $year);
                }

                if (! str_ends_with($resolvedPrefix, '/')) {
                    $resolvedPrefix .= '/';
                }

                return "{$resolvedPrefix}{$periodPart}-{$formattedNumber}";
            }

            return "{$resolvedPrefix}{$formattedNumber}";
        });
    }
}

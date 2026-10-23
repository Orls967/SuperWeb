<?php

declare(strict_types=1);

namespace Modules\Asset\Application\Actions;

use Carbon\Carbon;
use Modules\Asset\Domain\Models\Asset;
use Modules\Asset\Domain\Models\AssetEvent;
use Modules\Shared\Application\BaseAction;

class VerifyAssetHashChainAction extends BaseAction
{
    /**
     * @param  array<string, mixed>  $params
     * @return array{is_valid: bool, total_events: int, tampered_sequence: int|null, error: string|null}
     */
    public function execute(array $params): array
    {
        $asset = Asset::findOrFail($params['asset_id']);
        $events = AssetEvent::where('asset_id', $asset->id)
            ->orderBy('sequence')
            ->get();

        if ($events->isEmpty()) {
            return [
                'is_valid' => true,
                'total_events' => 0,
                'tampered_sequence' => null,
                'error' => null,
            ];
        }

        $expectedPrevHash = RegisterFixedAssetAction::GENESIS_HASH;
        $expectedSeq = 1;

        foreach ($events as $event) {
            if ($event->sequence !== $expectedSeq) {
                return [
                    'is_valid' => false,
                    'total_events' => $events->count(),
                    'tampered_sequence' => $event->sequence,
                    'error' => "Urutan sekuens tidak beraturan: ditemukan {$event->sequence}, diharapkan {$expectedSeq}",
                ];
            }

            if ($event->prev_hash !== $expectedPrevHash) {
                return [
                    'is_valid' => false,
                    'total_events' => $events->count(),
                    'tampered_sequence' => $event->sequence,
                    'error' => "Hash sebelumnya tidak cocok pada sekuens {$event->sequence}",
                ];
            }

            $occurredIso = Carbon::parse($event->occurred_at)->toIso8601String();
            $payloadJson = json_encode($event->payload, JSON_THROW_ON_ERROR);

            $recomputedHash = AssetEvent::calculateHash(
                $event->prev_hash,
                $event->sequence,
                $event->event_type->value,
                $payloadJson,
                $occurredIso
            );

            if ($recomputedHash !== $event->hash) {
                return [
                    'is_valid' => false,
                    'total_events' => $events->count(),
                    'tampered_sequence' => $event->sequence,
                    'error' => "Integritas hash terkompromi pada sekuens {$event->sequence}",
                ];
            }

            $expectedPrevHash = $event->hash;
            $expectedSeq++;
        }

        return [
            'is_valid' => true,
            'total_events' => $events->count(),
            'tampered_sequence' => null,
            'error' => null,
        ];
    }
}

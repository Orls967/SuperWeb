<?php

declare(strict_types=1);

namespace Modules\Plm\Application\Actions;

use Illuminate\Support\Str;
use InvalidArgumentException;
use Modules\Plm\Domain\Models\ChangeOrder;
use Modules\Plm\Domain\Models\EngineeringBom;

class ExecuteCryptographicEcoChangeOrderAction
{
    /**
     * @param  string  $disposition  'scrap'|'rework'|'run_out'
     * @return array{
     *     change_order: ChangeOrder,
     *     new_version: string,
     *     hash: string,
     *     prev_hash: string
     * }
     */
    public function execute(
        EngineeringBom $ebom,
        string $title,
        string $reason,
        int $costImpactIdr,
        string $disposition = 'scrap',
        string $authorizerSignature = 'CHIEF-ENG-SIG'
    ): array {
        if (empty($title) || empty($reason)) {
            throw new InvalidArgumentException('ECO title and technical engineering reason are required.');
        }

        $validDispositions = ['scrap', 'rework', 'run_out'];
        if (! in_array($disposition, $validDispositions, true)) {
            throw new InvalidArgumentException("Invalid disposition: {$disposition}.");
        }

        $lastEco = ChangeOrder::where('ebom_id', $ebom->id)->latest('created_at')->first();
        $prevHash = $lastEco ? $lastEco->hash : hash('sha256', "GENESIS-ECO-{$ebom->id}");

        $payload = "{$prevHash}|{$ebom->id}|{$title}|{$costImpactIdr}|{$disposition}|{$authorizerSignature}";
        $hash = hash('sha256', $payload);

        $eco = ChangeOrder::create([
            'id' => (string) Str::uuid(),
            'eco_number' => 'ECO-'.strtoupper(Str::random(8)),
            'ebom_id' => $ebom->id,
            'title' => $title,
            'reason' => $reason,
            'cost_impact_idr' => $costImpactIdr,
            'disposition' => $disposition,
            'prev_hash' => $prevHash,
            'hash' => $hash,
            'status' => 'approved',
        ]);

        // Increment minor version, e.g. v1.0 -> v1.1
        $currentVer = $ebom->version ?? 'v1.0';
        $parts = explode('.', str_replace('v', '', $currentVer));
        $major = (int) ($parts[0] ?? 1);
        $minor = ((int) ($parts[1] ?? 0)) + 1;
        $newVer = "v{$major}.{$minor}";

        $ebom->update(['version' => $newVer]);

        return [
            'change_order' => $eco,
            'new_version' => $newVer,
            'hash' => $hash,
            'prev_hash' => $prevHash,
        ];
    }
}

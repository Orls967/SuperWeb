<?php

declare(strict_types=1);

namespace Modules\Esg\Application\Services;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Esg\Contracts\CarbonCreditIssuer;

final class CarbonCreditIssuerService implements CarbonCreditIssuer
{
    public function issueRestorationCredits(int $forestPlotId, int $kgCo2e, int $vintageYear, string $idempotencyKey): int
    {
        if ($forestPlotId <= 0 || $kgCo2e <= 0 || $vintageYear < 2000 || $idempotencyKey === '') {
            throw new InvalidArgumentException('Parameter penerbitan kredit karbon tidak valid.');
        }

        $existing = DB::table('esg_carbon_issuances')->where('idempotency_key', $idempotencyKey)->first();
        if ($existing) {
            return (int) $existing->id;
        }

        return (int) DB::table('esg_carbon_issuances')->insertGetId([
            'forest_plot_ref' => $forestPlotId,
            'kg_co2e' => $kgCo2e,
            'vintage_year' => $vintageYear,
            'idempotency_key' => $idempotencyKey,
            'status' => 'issued',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}

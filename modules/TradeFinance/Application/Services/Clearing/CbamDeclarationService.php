<?php

declare(strict_types=1);

namespace Modules\TradeFinance\Application\Services\Clearing;

use App\Models\User;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Core\Contracts\ClockInterface;
use Modules\Core\Contracts\DocumentNumberingInterface;
use Modules\Core\Contracts\DocumentStoreInterface;
use Modules\Esg\Contracts\VerifiedEmissionReader;
use Modules\TradeFinance\Domain\Exceptions\IdempotencyConflictException;
use Modules\TradeFinance\Domain\Models\Clearing\CbamDeclaration;
use Modules\TradeFinance\Domain\Models\Clearing\CbamParameter;
use RuntimeException;

/**
 * D83-3 CBAM per kontainer ekspor ke UE.
 *
 * emisi tertanam (tCO2e) = massa (t) × emisi spesifik terverifikasi (K7), HalfUp 3 desimal.
 * Data terverifikasi tidak ada → ditolak (bukan input bebas). Parameter harga/kurs berversi berlabel S2.
 * Kompleksitas: O(1) per sertifikat.
 */
final class CbamDeclarationService
{
    public function __construct(
        private readonly VerifiedEmissionReader $emissions,
        private readonly DocumentNumberingInterface $numbering,
        private readonly DocumentStoreInterface $documents,
        private readonly ClockInterface $clock,
    ) {}

    /** Emisi tertanam dalam milli-tCO2e: kg × (tCO2e/t) / 1000 → 3 desimal HalfUp, dikali 1.000. */
    public static function embeddedMilliTco2e(int $netMassKg, string $specificTco2ePerTonne): int
    {
        return BigDecimal::of($netMassKg)
            ->multipliedBy($specificTco2ePerTonne)
            ->dividedBy(1000, 3, RoundingMode::HalfUp)
            ->getUnscaledValue()
            ->toInt();
    }

    public static function levyIdr(int $milliTco2e, int $priceEurCentsPerTco2e, int $eurToIdr): int
    {
        return BigDecimal::of($milliTco2e)
            ->multipliedBy($priceEurCentsPerTco2e)
            ->multipliedBy($eurToIdr)
            ->dividedBy(100_000, 0, RoundingMode::HalfUp)
            ->toInt();
    }

    public static function certificateHash(array $f): string
    {
        ksort($f);

        return hash('sha256', json_encode($f, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    public function declare(
        string $containerNumber,
        string $productCode,
        string $originCountry,
        int $netMassKg,
        string $idempotencyKey,
        ?User $actor = null
    ): CbamDeclaration {
        $key = trim($idempotencyKey);
        if ($key === '' || strlen($key) > 64) {
            throw new InvalidArgumentException('Idempotency-Key wajib (1–64 karakter).');
        }
        $containerNumber = strtoupper(trim($containerNumber));
        $productCode = strtoupper(trim($productCode));
        $originCountry = strtoupper(trim($originCountry));
        if ($netMassKg <= 0) {
            throw new InvalidArgumentException('Massa bersih (kg) harus bilangan bulat positif.');
        }

        return DB::transaction(function () use ($containerNumber, $productCode, $originCountry, $netMassKg, $key, $actor) {
            $existing = CbamDeclaration::where('idempotency_key', $key)->first();
            if ($existing !== null) {
                if ($existing->container_number !== $containerNumber || (int) $existing->net_mass_kg !== $netMassKg
                    || $existing->product_code !== $productCode || $existing->origin_country !== $originCountry) {
                    throw IdempotencyConflictException::forKey($key);
                }

                return $existing;
            }
            if (CbamDeclaration::where('container_number', $containerNumber)->exists()) {
                throw new RuntimeException("Kontainer {$containerNumber} sudah memiliki sertifikat CBAM.");
            }

            $at = CarbonImmutable::instance($this->clock->now());
            $emission = $this->emissions->specificEmissionPerTonne($productCode, $originCountry, $at);
            if ($emission === null) {
                throw new RuntimeException("Data emisi spesifik terverifikasi untuk {$productCode}/{$originCountry} tidak ada; sertifikat CBAM ditolak.");
            }

            $param = $this->parameterAt($at);
            $milli = self::embeddedMilliTco2e($netMassKg, $emission['t_co2e_per_t']);
            $levy = self::levyIdr($milli, (int) $param->price_eur_cents_per_tco2e, (int) $param->eur_to_idr_rate);

            $number = $this->numbering->nextNumber('TF', 'CBAM', false, 'CBAM/{YYYY}/', 6);

            $content = json_encode([
                'certificate_number' => $number, 'container_number' => $containerNumber,
                'product_code' => $productCode, 'origin_country' => $originCountry,
                'net_mass_kg' => $netMassKg, 'specific_t_co2e_per_t' => $emission['t_co2e_per_t'],
                'verification_ref' => $emission['verification_ref'], 'embedded_milli_tco2e' => $milli,
                'parameter_version' => $param->version, 'levy_idr' => $levy, 'simulation_label' => $param->label,
            ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

            $this->documents->store(
                file: $content,
                filename: 'cbam-'.str_replace('/', '-', $number).'.json',
                documentType: 'CBAM_CERT',
                uploadedBy: $actor,
                metadata: ['container_number' => $containerNumber, 'certificate_number' => $number],
            );
            $checksum = hash('sha256', $content);

            $hash = self::certificateHash([
                'certificate_number' => $number, 'container_number' => $containerNumber, 'product_code' => $productCode,
                'origin_country' => $originCountry, 'net_mass_kg' => $netMassKg,
                'specific_t_co2e_per_t' => $emission['t_co2e_per_t'], 'verification_ref' => $emission['verification_ref'],
                'embedded_milli_tco2e' => $milli, 'parameter_version' => $param->version, 'levy_idr' => $levy,
                'document_checksum' => $checksum,
            ]);

            return CbamDeclaration::create([
                'certificate_number' => $number,
                'container_number' => $containerNumber,
                'product_code' => $productCode,
                'origin_country' => $originCountry,
                'net_mass_kg' => $netMassKg,
                'specific_t_co2e_per_t' => $emission['t_co2e_per_t'],
                'verification_ref' => $emission['verification_ref'],
                'embedded_milli_tco2e' => $milli,
                'parameter_version' => $param->version,
                'levy_idr' => $levy,
                'simulation_label' => $param->label,
                'document_checksum' => $checksum,
                'certificate_hash' => $hash,
                'idempotency_key' => $key,
                'declared_by_user_id' => $actor?->id,
                'declared_at' => $at,
            ]);
        });
    }

    private function parameterAt(CarbonImmutable $at): CbamParameter
    {
        $atString = $at->utc()->toDateTimeString();
        $param = CbamParameter::query()
            ->where('effective_from', '<=', $atString)
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>', $atString))
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->first();

        if ($param === null) {
            throw new RuntimeException('Parameter CBAM berversi (S2) belum dikonfigurasi untuk tanggal ini.');
        }

        return $param;
    }

    /** @return Collection<int, CbamDeclaration> */
    public function recent(int $limit = 100)
    {
        return CbamDeclaration::query()->orderByDesc('id')->limit($limit)->get();
    }

    /** @return array{total:int, milli_tco2e:int, levy_idr:int} */
    public function summary(): array
    {
        $rows = CbamDeclaration::query()->orderBy('id')->get();

        return [
            'total' => $rows->count(),
            'milli_tco2e' => (int) $rows->sum('embedded_milli_tco2e'),
            'levy_idr' => (int) $rows->sum('levy_idr'),
        ];
    }
}

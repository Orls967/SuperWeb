<?php

declare(strict_types=1);

namespace Modules\Insurance\Application\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Core\Contracts\ClockInterface;
use Modules\Insurance\Domain\Exceptions\InsuranceDomainException;
use Modules\Insurance\Domain\Models\InsuranceParameter;

/**
 * Parameter aktuaria/regulasi berversi (G7-D1). Tidak ada nilai bawaan diam-diam (X25): parameter yang
 * tidak ditemukan pada tanggal efektif melempar galat. Di produksi, hanya parameter non-fixture yang telah
 * dikonfirmasi pemilik (`owner_confirmed_at`) yang boleh dipakai.
 */
final class ParameterRepository
{
    public function __construct(private readonly ClockInterface $clock) {}

    /**
     * Versi berlaku pada $at: effective_from terbesar ≤ tanggal itu; seri → versi tertinggi.
     */
    public function resolve(string $code, ?CarbonImmutable $at = null): InsuranceParameter
    {
        $date = ($at ?? CarbonImmutable::instance($this->clock->now()))->toDateString();

        $parameter = InsuranceParameter::query()
            ->where('code', $code)
            ->where('effective_from', '<=', $date)
            ->orderByDesc('effective_from')
            ->orderByDesc('version')
            ->first();

        if ($parameter === null) {
            throw new InsuranceDomainException("Parameter '{$code}' belum terdefinisi untuk tanggal {$date}.");
        }

        if (app()->environment('production') && ($parameter->is_fixture || $parameter->owner_confirmed_at === null)) {
            throw new InsuranceDomainException("Parameter '{$code}' v{$parameter->version} belum dikonfirmasi pemilik untuk produksi.");
        }

        return $parameter;
    }

    /** @return array<string, mixed> */
    public function value(string $code, ?CarbonImmutable $at = null): array
    {
        return $this->resolve($code, $at)->value;
    }

    /**
     * Terbitkan versi baru (append-only; nomor versi = max + 1 dalam transaksi).
     *
     * @param  array<string, mixed>  $value
     */
    public function publish(string $code, array $value, CarbonImmutable $effectiveFrom, string $source, string $label = 'S1', bool $isFixture = false, ?int $actorId = null, bool $ownerConfirmed = false): InsuranceParameter
    {
        if (trim($source) === '') {
            throw new InsuranceDomainException('Sumber parameter wajib diisi.');
        }

        return DB::transaction(function () use ($code, $value, $effectiveFrom, $source, $label, $isFixture, $actorId, $ownerConfirmed): InsuranceParameter {
            $next = ((int) InsuranceParameter::query()->where('code', $code)->lockForUpdate()->max('version')) + 1;

            return InsuranceParameter::create([
                'code' => $code,
                'version' => $next,
                'effective_from' => $effectiveFrom->toDateString(),
                'source' => $source,
                'simulation_label' => $label,
                'value' => $value,
                'is_fixture' => $isFixture,
                'owner_confirmed_at' => $ownerConfirmed ? $this->clock->now() : null,
                'created_by' => $actorId,
            ]);
        });
    }

    /** @return array<string, int> kode => versi berlaku, untuk disimpan sebagai snapshot input. */
    public function versions(array $codes, ?CarbonImmutable $at = null): array
    {
        $versions = [];
        foreach ($codes as $code) {
            $versions[$code] = $this->resolve($code, $at)->version;
        }

        return $versions;
    }
}

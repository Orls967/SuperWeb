<?php

declare(strict_types=1);

namespace Modules\Insurance\Application\Services;

use Closure;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Modules\Insurance\Domain\Exceptions\IdempotencyKeyConflictException;
use Modules\Insurance\Domain\Exceptions\InsuranceDomainException;
use Modules\Insurance\Domain\Models\RequestKey;

/**
 * Penjaga Idempotency-Key permintaan (U-4): key kosong → galat validasi; key sama + isi sama → tidak diulang
 * (mengembalikan null); key sama + isi beda → IdempotencyKeyConflictException. Unique constraint DB = pertahanan terakhir.
 */
final class IdempotentRequestGuard
{
    /**
     * @template T
     *
     * @param  array<string, mixed>  $payload
     * @param  Closure(): T  $operation
     * @return T|null null bila permintaan identik sudah pernah diproses
     */
    public function run(string $key, string $scope, array $payload, ?int $actorId, Closure $operation): mixed
    {
        $key = trim($key);
        if ($key === '' || strlen($key) > 120) {
            throw new InsuranceDomainException('Idempotency-Key wajib diisi (maks 120 karakter).');
        }

        ksort($payload);
        $hash = hash('sha256', $scope.'|'.json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));

        return DB::transaction(function () use ($key, $scope, $hash, $actorId, $operation): mixed {
            $existing = RequestKey::query()->where('idempotency_key', $key)->first();
            if ($existing !== null) {
                if ($existing->payload_hash !== $hash || $existing->scope !== $scope) {
                    throw new IdempotencyKeyConflictException($key);
                }

                return null;
            }

            try {
                RequestKey::create(['idempotency_key' => $key, 'scope' => $scope, 'payload_hash' => $hash, 'created_by' => $actorId]);
            } catch (UniqueConstraintViolationException) {
                return null;
            }

            return $operation();
        });
    }
}

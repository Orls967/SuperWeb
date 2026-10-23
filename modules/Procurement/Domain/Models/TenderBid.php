<?php

declare(strict_types=1);

namespace Modules\Procurement\Domain\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;
use Modules\Supplier\Contracts\Supplier;

class TenderBid extends Model
{
    protected $table = 'prc_tender_bids';

    protected $fillable = [
        'tender_id', 'supplier_id', 'seal_hash', 'sealed_at', 'offer_ciphertext', 'offer', 'opened_at',
        'total_score', 'is_winner', 'notes', 'approval_id',
    ];

    protected $casts = [
        'sealed_at' => 'datetime', 'opened_at' => 'datetime', 'offer' => 'array',
        'total_score' => 'decimal:4', 'is_winner' => 'boolean', 'approval_id' => 'integer',
    ];

    public function tender(): BelongsTo
    {
        return $this->belongsTo(Tender::class, 'tender_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    /**
     * Serialisasi JSON kanonik (urutkan key secara rekursif) agar hash deterministik.
     *
     * @param  array<string, mixed>  $data
     */
    public static function canonicalJson(array $data): string
    {
        $sortRecursive = static function (array &$arr) use (&$sortRecursive): void {
            foreach ($arr as &$value) {
                if (is_array($value)) {
                    $sortRecursive($value);
                }
            }
            ksort($arr);
        };

        $copy = $data;
        $sortRecursive($copy);

        return json_encode($copy, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Format timestamp ISO-8601 UTC detik untuk formula hash segel.
     */
    public static function formatSealedTimestamp(CarbonInterface $timestamp): string
    {
        return $timestamp->copy()->utc()->format('Y-m-d\TH:i:s\Z');
    }

    /**
     * Hitung hash segel SHA-256 dari array penawaran kanonik dan timestamp.
     *
     * @param  array<string, mixed>  $offer
     */
    public static function calculateSealHash(array $offer, CarbonInterface $sealedAt): string
    {
        $canonicalJson = self::canonicalJson($offer);
        $sealedAtIso = self::formatSealedTimestamp($sealedAt);

        return hash('sha256', $canonicalJson.'|'.$sealedAtIso);
    }

    /**
     * Verifikasi keutuhan segel penawaran.
     *
     * @param  array<string, mixed>|null  $candidateOffer
     */
    public function isSealIntact(?array $candidateOffer = null): bool
    {
        if ($this->sealed_at === null || empty($this->seal_hash)) {
            return false;
        }

        if ($candidateOffer === null) {
            if (! empty($this->offer_ciphertext)) {
                try {
                    $decrypted = Crypt::decryptString($this->offer_ciphertext);
                    $decoded = json_decode($decrypted, true, 512, JSON_THROW_ON_ERROR);
                    $candidateOffer = is_array($decoded) ? $decoded : null;
                } catch (\Throwable) {
                    return false;
                }
            } elseif (is_array($this->offer)) {
                $candidateOffer = $this->offer;
            } else {
                return false;
            }
        }

        if (! is_array($candidateOffer)) {
            return false;
        }

        $expectedHash = self::calculateSealHash($candidateOffer, $this->sealed_at);

        return hash_equals($this->seal_hash, $expectedHash);
    }
}

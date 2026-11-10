<?php

declare(strict_types=1);

namespace Modules\EnterpriseFinance\Application\Services;

use Carbon\CarbonImmutable;
use InvalidArgumentException;
use Psr\Clock\ClockInterface;

/**
 * Pengelola Kontrol Penyeimbang Segregation of Duties (Mitigating Controls Manager) (54.4).
 * Mengelola dispensasi sementara untuk perangkapan peran darurat yang telah disetujui
 * oleh Komite Audit atau Direktur Keuangan dengan kompensasi pengawasan ekstra.
 */
class MitigatingControlsManager
{
    /**
     * @var list<array{
     *     control_id: string,
     *     rule_code: string,
     *     user_id: int,
     *     approver_id: int,
     *     justification: string,
     *     valid_until: string,
     *     is_active: bool
     * }>
     */
    protected static array $registry = [];

    public function __construct(
        protected readonly ?ClockInterface $clock = null
    ) {}

    protected function now(): CarbonImmutable
    {
        if ($this->clock !== null) {
            return CarbonImmutable::instance($this->clock->now());
        }

        return CarbonImmutable::now();
    }

    /**
     * Mendaftarkan persetujuan dispensasi peran dengan kontrol penyeimbang.
     */
    public function registerMitigatingControl(
        string $ruleCode,
        int $userId,
        int $approverId,
        string $justification,
        int $validityDays = 14
    ): array {
        if ($userId === $approverId) {
            throw new InvalidArgumentException('Pemberi dispensasi kontrol tidak boleh sama dengan pengguna yang menerima dispensasi.');
        }

        $controlId = 'MIT-'.strtoupper(bin2hex(random_bytes(4)));
        $validUntil = $this->now()->addDays($validityDays)->toIso8601String();

        $entry = [
            'control_id' => $controlId,
            'rule_code' => $ruleCode,
            'user_id' => $userId,
            'approver_id' => $approverId,
            'justification' => $justification,
            'valid_until' => $validUntil,
            'is_active' => true,
        ];

        self::$registry[] = $entry;

        return $entry;
    }

    /**
     * Memeriksa apakah pengguna memiliki dispensasi aktif untuk aturan SoD tertentu.
     */
    public function hasActiveMitigation(int $userId, string $ruleCode): bool
    {
        $nowStr = $this->now()->toIso8601String();

        foreach (self::$registry as $item) {
            if ($item['user_id'] === $userId && $item['rule_code'] === $ruleCode && $item['is_active']) {
                if ($item['valid_until'] >= $nowStr) {
                    return true;
                }
            }
        }

        return false;
    }
}

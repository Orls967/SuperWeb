<?php

declare(strict_types=1);

namespace Modules\Egy\Domain\StateMachines;

use InvalidArgumentException;
use Modules\Egy\Domain\Models\EscoContract;

final class EscoContractStateMachine
{
    public const STATUS_AUDITED = 'audited';

    public const STATUS_PROPOSED = 'proposed';

    public const STATUS_CONTRACTED = 'contracted';

    public const STATUS_IMPLEMENTING = 'implementing';

    public const STATUS_MEASURING = 'measuring';

    public const STATUS_SETTLED = 'settled';

    public const STATUS_TERMINATED = 'terminated';

    /**
     * @var array<string, list<string>>
     */
    private const ALLOWED_TRANSITIONS = [
        self::STATUS_AUDITED => [self::STATUS_PROPOSED, self::STATUS_TERMINATED],
        self::STATUS_PROPOSED => [self::STATUS_CONTRACTED, self::STATUS_TERMINATED],
        self::STATUS_CONTRACTED => [self::STATUS_IMPLEMENTING, self::STATUS_TERMINATED],
        self::STATUS_IMPLEMENTING => [self::STATUS_MEASURING, self::STATUS_TERMINATED],
        self::STATUS_MEASURING => [self::STATUS_SETTLED, self::STATUS_TERMINATED],
        self::STATUS_SETTLED => [self::STATUS_MEASURING, self::STATUS_TERMINATED],
        self::STATUS_TERMINATED => [],
    ];

    public function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::ALLOWED_TRANSITIONS[$from] ?? [], true);
    }

    public function transition(EscoContract $contract, string $to): void
    {
        $current = $contract->status;

        if (! $this->canTransition($current, $to)) {
            throw new InvalidArgumentException("Transisi tidak sah dari status '{$current}' ke '{$to}'");
        }

        $contract->status = $to;
        $contract->save();
    }
}

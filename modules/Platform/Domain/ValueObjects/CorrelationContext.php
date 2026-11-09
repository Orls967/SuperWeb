<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\ValueObjects;

use InvalidArgumentException;

/**
 * Value Object immutable representasi konteks korelasi terdistribusi W3C TraceContext.
 */
final class CorrelationContext
{
    /**
     * @param  array<string, string>  $baggage
     */
    public function __construct(
        public readonly string $traceId,
        public readonly string $spanId,
        public readonly string $traceFlags = '01',
        public readonly array $baggage = []
    ) {
        if (strlen($this->traceId) !== 32) {
            throw new InvalidArgumentException("Trace ID wajib berpanjang 32 karakter heksadesimal: {$this->traceId}");
        }
        if (strlen($this->spanId) !== 16) {
            throw new InvalidArgumentException("Span ID wajib berpanjang 16 karakter heksadesimal: {$this->spanId}");
        }
    }

    public static function createNew(bool $sampled = true): self
    {
        $traceId = bin2hex(random_bytes(16));
        $spanId = bin2hex(random_bytes(8));
        $flags = $sampled ? '01' : '00';

        return new self($traceId, $spanId, $flags);
    }

    public function toTraceparentHeader(): string
    {
        return sprintf('00-%s-%s-%s', $this->traceId, $this->spanId, $this->traceFlags);
    }

    public function createChildSpan(bool $sampled = true): self
    {
        $childSpanId = bin2hex(random_bytes(8));
        $flags = $sampled ? '01' : '00';

        return new self($this->traceId, $childSpanId, $flags, $this->baggage);
    }

    public function withBaggageItem(string $key, string $value): self
    {
        $newBaggage = $this->baggage;
        $newBaggage[$key] = $value;

        return new self($this->traceId, $this->spanId, $this->traceFlags, $newBaggage);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'trace_id' => $this->traceId,
            'span_id' => $this->spanId,
            'trace_flags' => $this->traceFlags,
            'baggage' => $this->baggage,
            'header' => $this->toTraceparentHeader(),
        ];
    }
}

<?php

declare(strict_types=1);

namespace Modules\Core\Application\Services;

use Illuminate\Support\Str;

/**
 * Konteks Penelusuran Terdistribusi (EventTracingContext)
 *
 * Mengelola identitas penelusuran (Correlation ID, Causation ID, Baggage Context)
 * yang melintasi batasan proses asynchronous di Universal Event Spine.
 */
class EventTracingContext
{
    protected ?string $correlationId = null;
    protected ?string $causationId = null;

    /**
     * @var array<string, string>
     */
    protected array $baggage = [];

    /**
     * Memulai rantai penelusuran baru dengan UUID korelasi segar.
     */
    public function initiate(?string $correlationId = null): self
    {
        $this->correlationId = $correlationId ?? (string) Str::uuid();
        $this->causationId = null;
        $this->baggage = [];

        return $this;
    }

    /**
     * Mengalirkan korelasi dari event induk (causal parent).
     */
    public function inheritFrom(string $correlationId, ?string $parentEventId = null, array $baggage = []): self
    {
        $this->correlationId = $correlationId;
        $this->causationId = $parentEventId;
        $this->baggage = $baggage;

        return $this;
    }

    public function getCorrelationId(): string
    {
        if ($this->correlationId === null) {
            $this->correlationId = (string) Str::uuid();
        }

        return $this->correlationId;
    }

    public function getCausationId(): ?string
    {
        return $this->causationId;
    }

    /**
     * @return array<string, string>
     */
    public function getBaggage(): array
    {
        return $this->baggage;
    }

    public function setBaggageItem(string $key, string $value): self
    {
        $this->baggage[$key] = $value;

        return $this;
    }

    public function getBaggageItem(string $key, ?string $default = null): ?string
    {
        return $this->baggage[$key] ?? $default;
    }

    /**
     * Mengekspor konteks lengkap ke format array untuk disisipkan ke header envelope event spine.
     *
     * @return array{correlation_id: string, causation_id: string|null, baggage: array<string, string>}
     */
    public function exportHeaders(): array
    {
        return [
            'correlation_id' => $this->getCorrelationId(),
            'causation_id' => $this->getCausationId(),
            'baggage' => $this->baggage,
        ];
    }
}

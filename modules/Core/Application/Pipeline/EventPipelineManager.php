<?php

declare(strict_types=1);

namespace Modules\Core\Application\Pipeline;

use Closure;
use Modules\Core\Domain\Models\SimEventSpine;

/**
 * Pengelola Pipeline Eksekusi Event Spine
 *
 * Menerapkan pola Pipeline (Chain of Responsibility) untuk memproses
 * event melalui serangkaian middleware deterministik sebelum mencapai consumer handler.
 */
class EventPipelineManager
{
    /**
     * @var array<int, class-string<SpineMiddlewareInterface>|SpineMiddlewareInterface>
     */
    protected array $middleware = [];

    /**
     * @param  array<int, class-string<SpineMiddlewareInterface>|SpineMiddlewareInterface>  $middleware
     */
    public function __construct(array $middleware = [])
    {
        $this->middleware = $middleware;
    }

    /**
     * Menambahkan middleware ke dalam pipeline eksekusi.
     *
     * @param  class-string<SpineMiddlewareInterface>|SpineMiddlewareInterface  $pipe
     */
    public function pipe(string|SpineMiddlewareInterface $pipe): self
    {
        $this->middleware[] = $pipe;

        return $this;
    }

    /**
     * Eksekusi event melalui seluruh middleware terdaftar.
     *
     * @param  Closure(SimEventSpine): mixed  $destination
     */
    public function send(SimEventSpine $event, string $consumerGroup, Closure $destination): mixed
    {
        $pipeline = array_reduce(
            array_reverse($this->middleware),
            function (Closure $next, string|SpineMiddlewareInterface $pipe) {
                return function (SimEventSpine $event, string $consumerGroup) use ($next, $pipe) {
                    $instance = is_string($pipe) ? app($pipe) : $pipe;

                    return $instance->handle($event, $consumerGroup, $next);
                };
            },
            function (SimEventSpine $event, string $consumerGroup) use ($destination) {
                return $destination($event);
            }
        );

        return $pipeline($event, $consumerGroup);
    }

    /**
     * @return array<int, class-string<SpineMiddlewareInterface>|SpineMiddlewareInterface>
     */
    public function getMiddleware(): array
    {
        return $this->middleware;
    }
}


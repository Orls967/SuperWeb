<?php

declare(strict_types=1);

namespace Modules\Core\Application\Pipeline;

use Closure;
use Modules\Core\Domain\Models\SimEventSpine;

/**
 * Antarmuka Middleware Event Spine
 */
interface SpineMiddlewareInterface
{
    /**
     * Menjalankan interceptor pada aliran pesan event spine.
     *
     * @param  Closure(SimEventSpine, string): mixed  $next
     */
    public function handle(SimEventSpine $event, string $consumerGroup, Closure $next): mixed;
}


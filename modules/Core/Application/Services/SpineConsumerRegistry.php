<?php

declare(strict_types=1);

namespace Modules\Core\Application\Services;

use Closure;
use Modules\Core\Domain\Models\SimEventSpine;

class SpineConsumerRegistry
{
    /**
     * @var array<string, array<string, array<int, Closure|string>>>
     */
    protected static array $handlers = [];

    /**
     * @var array<string, array<string, bool>>
     */
    protected static array $processedEvents = [];

    /**
     * Register a handler for a consumer group and event name or topic pattern.
     */
    public static function register(string $group, string $eventName, Closure|string $handler): void
    {
        self::$handlers[$group][$eventName][] = $handler;
    }

    /**
     * Dispatch an event to registered handlers for this consumer group.
     * Returns true if handled, false if no specific handler was registered.
     */
    public static function dispatch(string $group, SimEventSpine $event): bool
    {
        $idempotencyKey = "{$group}:{$event->event_id}";
        if (isset(self::$processedEvents[$group][$event->event_id])) {
            // Already processed in this execution run (idempotent replay)
            return true;
        }

        $handled = false;
        $candidates = [
            $event->event_name,
            $event->topic,
            '*',
        ];

        foreach ($candidates as $candidate) {
            if (isset(self::$handlers[$group][$candidate])) {
                foreach (self::$handlers[$group][$candidate] as $handler) {
                    if ($handler instanceof Closure) {
                        $handler($event);
                    } elseif (is_string($handler) && class_exists($handler)) {
                        $instance = app($handler);
                        if (method_exists($instance, 'handle')) {
                            $instance->handle($event);
                        } elseif (is_callable($instance)) {
                            $instance($event);
                        }
                    }
                    $handled = true;
                }
            }
        }

        self::$processedEvents[$group][$event->event_id] = true;

        return $handled;
    }

    /**
     * Clear registered handlers and processed event cache (useful for tests).
     */
    public static function reset(): void
    {
        self::$handlers = [];
        self::$processedEvents = [];
    }

    /**
     * Check if a specific event has been processed by group.
     */
    public static function hasProcessed(string $group, string $eventId): bool
    {
        return isset(self::$processedEvents[$group][$eventId]);
    }
}

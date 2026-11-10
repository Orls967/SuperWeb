<?php

declare(strict_types=1);

namespace Modules\Core\Application\Routing;

use Closure;
use Modules\Core\Domain\Models\SimEventSpine;

/**
 * Router Event Berbasis Konten Payload (ContentBasedEventRouter)
 *
 * Menerapkan evaluasi predikat bersyarat (kriteria filter dinamis)
 * pada payload event untuk menentukan apakah event relevan bagi konsumen tertentu.
 */
class ContentBasedEventRouter
{
    /**
     * @var array<string, array<int, array{predicate: Closure, destination: string}>>
     */
    protected array $routes = [];

    public function __construct(
        protected TopicPatternMatcher $matcher
    ) {}

    /**
     * Mendaftarkan rute berbasis predikat untuk pola topik tertentu.
     *
     * @param  Closure(array<string, mixed>): bool  $predicate
     */
    public function addRoute(string $topicPattern, Closure $predicate, string $destinationGroup): self
    {
        $this->routes[$topicPattern][] = [
            'predicate' => $predicate,
            'destination' => $destinationGroup,
        ];

        return $this;
    }

    /**
     * Menentukan daftar grup konsumen tujuan yang memenuhi kriteria predikat.
     *
     * @return array<int, string>
     */
    public function route(SimEventSpine $event): array
    {
        $matchedGroups = [];
        $payload = (array) $event->payload;
        $topic = (string) $event->topic;

        foreach ($this->routes as $pattern => $routeDefs) {
            if ($this->matcher->matches($pattern, $topic)) {
                foreach ($routeDefs as $routeDef) {
                    $predicate = $routeDef['predicate'];
                    if ($predicate($payload) === true) {
                        $matchedGroups[] = $routeDef['destination'];
                    }
                }
            }
        }

        return array_values(array_unique($matchedGroups));
    }

    /**
     * Menghapus seluruh rute terdaftar (berguna untuk testing).
     */
    public function reset(): void
    {
        $this->routes = [];
    }
}

<?php

declare(strict_types=1);

use Modules\Shared\Domain\Algorithms\Geo\PointInPolygon;
use Random\Engine\Xoshiro256StarStar;
use Random\Randomizer;

covers(PointInPolygon::class);

describe('PointInPolygon', function (): void {
    it('validates polygon size and coordinate bounds', function (): void {
        $pip = new PointInPolygon;

        expect(fn () => $pip->contains([['x' => 0, 'y' => 0], ['x' => 1, 'y' => 1]], ['x' => 0, 'y' => 0]))
            ->toThrow(InvalidArgumentException::class);

        expect(fn () => $pip->contains([
            ['x' => 0, 'y' => 0],
            ['x' => 10, 'y' => 0],
            ['x' => 0, 'y' => 10],
        ], ['x' => 200_000_000, 'y' => 0]))
            ->toThrow(InvalidArgumentException::class);

        expect(fn () => $pip->contains([
            ['x' => 0, 'y' => -200_000_000],
            ['x' => 10, 'y' => 0],
            ['x' => 0, 'y' => 10],
        ], ['x' => 0, 'y' => 0]))
            ->toThrow(InvalidArgumentException::class);

        // Exact boundary values
        $validTriangle = [
            ['x' => PointInPolygon::MIN_COORD, 'y' => PointInPolygon::MIN_COORD],
            ['x' => PointInPolygon::MAX_COORD, 'y' => PointInPolygon::MIN_COORD],
            ['x' => 0, 'y' => PointInPolygon::MAX_COORD],
        ];
        expect($pip->contains($validTriangle, ['x' => 0, 'y' => 0]))->toBeTrue();
    });

    it('tests segment collinearity and diagonal boundary edge cases', function (): void {
        $pip = new PointInPolygon;

        $triangle = [
            ['x' => 0, 'y' => 0],
            ['x' => 10, 'y' => 10],
            ['x' => 0, 'y' => 10],
        ];

        // On diagonal edge (5, 5) -> inside
        expect($pip->contains($triangle, ['x' => 5, 'y' => 5]))->toBeTrue();

        // Collinear with diagonal edge but outside bounding box: (12, 12) -> outside
        expect($pip->contains($triangle, ['x' => 12, 'y' => 12]))->toBeFalse();

        // Collinear with diagonal edge but negative: (-2, -2) -> outside
        expect($pip->contains($triangle, ['x' => -2, 'y' => -2]))->toBeFalse();

        // Near diagonal edge but cross != 0: (5, 6) -> inside triangle, (6, 5) -> outside triangle
        expect($pip->contains($triangle, ['x' => 5, 'y' => 6]))->toBeTrue();
        expect($pip->contains($triangle, ['x' => 6, 'y' => 5]))->toBeFalse();

        // Second vertex directly (10, 10)
        expect($pip->contains($triangle, ['x' => 10, 'y' => 10]))->toBeTrue();
        // Third vertex directly (0, 10)
        expect($pip->contains($triangle, ['x' => 0, 'y' => 10]))->toBeTrue();

        // On horizontal top edge: (5, 10) -> inside
        expect($pip->contains($triangle, ['x' => 5, 'y' => 10]))->toBeTrue();
        // On vertical left edge: (0, 5) -> inside
        expect($pip->contains($triangle, ['x' => 0, 'y' => 5]))->toBeTrue();
    });

    it('tests ray casting crossing logic for upward and downward edges', function (): void {
        $pip = new PointInPolygon;

        // Diamond shaped polygon
        $diamond = [
            ['x' => 0, 'y' => 5],   // left
            ['x' => 5, 'y' => 10],  // top
            ['x' => 10, 'y' => 5],  // right
            ['x' => 5, 'y' => 0],   // bottom
        ];

        // Center inside
        expect($pip->contains($diamond, ['x' => 5, 'y' => 5]))->toBeTrue();

        // Left outside
        expect($pip->contains($diamond, ['x' => -1, 'y' => 5]))->toBeFalse();

        // Right outside
        expect($pip->contains($diamond, ['x' => 11, 'y' => 5]))->toBeFalse();

        // Test downward edge crossing when ray is cast
        // Triangle pointing right
        $triRight = [
            ['x' => 0, 'y' => 0],
            ['x' => 10, 'y' => 5],
            ['x' => 0, 'y' => 10],
        ];
        // Point (2, 5) is inside: ray to right crosses edge (10,5)-(0,10) which is downward
        expect($pip->contains($triRight, ['x' => 2, 'y' => 5]))->toBeTrue();
        // Point (12, 5) is to the right of the triangle -> outside
        expect($pip->contains($triRight, ['x' => 12, 'y' => 5]))->toBeFalse();
    });

    it('matches mandatory test vectors for square and boundary points', function (): void {
        $pip = new PointInPolygon;
        $scale = 1_000_000;

        $square = [
            ['x' => 0 * $scale, 'y' => 0 * $scale],
            ['x' => 10 * $scale, 'y' => 0 * $scale],
            ['x' => 10 * $scale, 'y' => 10 * $scale],
            ['x' => 0 * $scale, 'y' => 10 * $scale],
        ];

        // (5,5) inside
        expect($pip->contains($square, ['x' => 5 * $scale, 'y' => 5 * $scale]))->toBeTrue();

        // (0,5) on edge -> inside
        expect($pip->contains($square, ['x' => 0 * $scale, 'y' => 5 * $scale]))->toBeTrue();

        // (10,10) on corner -> inside
        expect($pip->contains($square, ['x' => 10 * $scale, 'y' => 10 * $scale]))->toBeTrue();

        // (11,5) outside
        expect($pip->contains($square, ['x' => 11 * $scale, 'y' => 5 * $scale]))->toBeFalse();
    });

    it('matches mandatory test vector for concave L-shaped polygon', function (): void {
        $pip = new PointInPolygon;
        $scale = 1_000_000;

        // L-shaped polygon
        $lShape = [
            ['x' => 0 * $scale, 'y' => 0 * $scale],
            ['x' => 6 * $scale, 'y' => 0 * $scale],
            ['x' => 6 * $scale, 'y' => 3 * $scale],
            ['x' => 3 * $scale, 'y' => 3 * $scale],
            ['x' => 3 * $scale, 'y' => 6 * $scale],
            ['x' => 0 * $scale, 'y' => 6 * $scale],
        ];

        // Point inside lower-left wing: (1, 1) -> inside
        expect($pip->contains($lShape, ['x' => 1 * $scale, 'y' => 1 * $scale]))->toBeTrue();

        // Point in the reflex corner notch: (5, 5) -> outside
        expect($pip->contains($lShape, ['x' => 5 * $scale, 'y' => 5 * $scale]))->toBeFalse();

        // Point on reflex corner vertex: (3, 3) -> inside
        expect($pip->contains($lShape, ['x' => 3 * $scale, 'y' => 3 * $scale]))->toBeTrue();
    });

    it('property test: cyclic rotation and direction reversal invariance', function (): void {
        $pip = new PointInPolygon;
        $scale = 1_000_000;

        $polygon = [
            ['x' => 0 * $scale, 'y' => 0 * $scale],
            ['x' => 8 * $scale, 'y' => 1 * $scale],
            ['x' => 7 * $scale, 'y' => 6 * $scale],
            ['x' => 4 * $scale, 'y' => 3 * $scale],
            ['x' => 1 * $scale, 'y' => 7 * $scale],
        ];

        $testPoints = [
            ['x' => 3 * $scale, 'y' => 2 * $scale], // interior
            ['x' => 5 * $scale, 'y' => 5 * $scale], // notch exterior
            ['x' => 0 * $scale, 'y' => 0 * $scale], // vertex
            ['x' => 10 * $scale, 'y' => 10 * $scale], // exterior
        ];

        $seed = 11223344;
        $randomizer = new Randomizer(new Xoshiro256StarStar($seed));

        $n = count($polygon);
        for ($shift = 0; $shift < $n; $shift++) {
            $rotated = array_merge(array_slice($polygon, $shift), array_slice($polygon, 0, $shift));
            $reversed = array_reverse($rotated);

            foreach ($testPoints as $pt) {
                $base = $pip->contains($polygon, $pt);
                expect($pip->contains($rotated, $pt))->toBe($base)
                    ->and($pip->contains($reversed, $pt))->toBe($base);
            }
        }
    });

    it('benchmarks 1000 vertex polygon point test < 0.2s', function (): void {
        $pip = new PointInPolygon;
        $scale = 100_000;
        $n = 1000;
        $poly = [];

        for ($i = 0; $i < $n; $i++) {
            $angle = 2 * M_PI * $i / $n;
            $poly[] = [
                'x' => (int) round(cos($angle) * $scale),
                'y' => (int) round(sin($angle) * $scale),
            ];
        }

        $start = microtime(true);
        for ($i = 0; $i < 100; $i++) {
            $pip->contains($poly, ['x' => 0, 'y' => 0]);
            $pip->contains($poly, ['x' => 2 * $scale, 'y' => 2 * $scale]);
        }
        $elapsed = microtime(true) - $start;

        expect($elapsed)->toBeLessThan(0.5);
    })->group('skala');
});

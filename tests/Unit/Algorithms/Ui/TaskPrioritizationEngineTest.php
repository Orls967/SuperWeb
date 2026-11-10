<?php

declare(strict_types=1);

namespace Tests\Unit\Algorithms\Ui;

use DateTimeImmutable;
use Modules\Shared\Domain\Algorithms\Ui\PriorityCertificateVerifier;
use Modules\Shared\Domain\Algorithms\Ui\TaskPrioritizationEngine;
use PHPUnit\Framework\TestCase;

class TaskPrioritizationEngineTest extends TestCase
{
    private TaskPrioritizationEngine $engine;

    private PriorityCertificateVerifier $verifier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->engine = new TaskPrioritizationEngine;
        $this->verifier = new PriorityCertificateVerifier;
    }

    public function test_compute_priority_scores_and_verifies_certificate(): void
    {
        $now = new DateTimeImmutable('2026-11-10 12:00:00');
        $dueIn3Hours = new DateTimeImmutable('2026-11-10 15:00:00');
        $overdue = new DateTimeImmutable('2026-11-10 10:00:00');

        $normal = $this->engine->computePriority('task-1', 'normal', 0, $dueIn3Hours, $now);
        $urgent = $this->engine->computePriority('task-2', 'urgent', 2, $overdue, $now);

        $this->assertGreaterThan($normal['score'], $urgent['score']);
        $this->assertTrue($this->verifier->verify($normal['certificate']));
        $this->assertTrue($this->verifier->verify($urgent['certificate']));

        // Sabotase payload
        $fake = $urgent['certificate'];
        $fake['payload']['score'] = 1.0;
        $this->assertFalse($this->verifier->verify($fake));
    }
}

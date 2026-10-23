<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_that_composer_json_exists(): void
    {
        $this->assertFileExists(dirname(__DIR__, 2).'/composer.json');
    }
}

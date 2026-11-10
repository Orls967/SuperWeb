<?php

declare(strict_types=1);

namespace Tests\Unit\Algorithms\Ui;

use Modules\Shared\Domain\Algorithms\Ui\FacetedCertificateVerifier;
use Modules\Shared\Domain\Algorithms\Ui\FacetedSearchAggregator;
use PHPUnit\Framework\TestCase;

class FacetedSearchAggregatorTest extends TestCase
{
    private FacetedSearchAggregator $aggregator;

    private FacetedCertificateVerifier $verifier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->aggregator = new FacetedSearchAggregator;
        $this->verifier = new FacetedCertificateVerifier;
    }

    public function test_aggregate_facets_and_certificate(): void
    {
        $docs = [
            ['id' => '1', 'module' => 'Contract', 'entity_type' => 'Agreement', 'status' => 'active'],
            ['id' => '2', 'module' => 'Contract', 'entity_type' => 'Addendum', 'status' => 'active'],
            ['id' => '3', 'module' => 'Asset', 'entity_type' => 'Machinery', 'status' => 'maintenance'],
        ];

        $res = $this->aggregator->aggregate($docs, ['module', 'entity_type', 'status']);

        $this->assertEquals(3, $res['total_items']);
        $this->assertEquals(2, $res['facets']['module']['Contract']);
        $this->assertEquals(1, $res['facets']['module']['Asset']);
        $this->assertEquals(2, $res['facets']['status']['active']);

        $this->assertTrue($this->verifier->verify($res['certificate']));

        // Sabotase payload sertifikat
        $corrupt = $res['certificate'];
        $corrupt['payload']['total_items'] = 999;
        $this->assertFalse($this->verifier->verify($corrupt));
    }
}

<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Modules\Integration\Application\Services\EnterpriseSearchKnowledgeDocOpsService;
use Tests\TestCase;

class EnterpriseSearchKnowledgeDocOpsTest extends TestCase
{
    use RefreshDatabase;

    protected EnterpriseSearchKnowledgeDocOpsService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(EnterpriseSearchKnowledgeDocOpsService::class);
    }

    public function test_document_lifecycle_superseded_redirect_and_search_metrics(): void
    {
        // 433.1 Register initial SOP document with legal ACL
        $this->service->registerDocument(
            docCode: 'SOP-AML-2024',
            title: 'Customer Due Diligence Operational Guidelines 2024',
            corpus: 'legal',
            version: '1.0',
            aclAllowedRoles: ['compliance_officer', 'legal_counsel', 'auditor']
        );

        // Register revised 2026 SOP
        $this->service->registerDocument(
            docCode: 'SOP-AML-2026',
            title: 'Customer Due Diligence Operational Guidelines 2026 (Revised)',
            corpus: 'legal',
            version: '2.0',
            aclAllowedRoles: ['compliance_officer', 'legal_counsel', 'auditor']
        );

        // 433.3 Supersede old SOP
        $this->service->supersedeDocument('SOP-AML-2024', 'SOP-AML-2026');

        // 433.5 Edge case: Fetching superseded document redirects automatically to 2026 version
        $fetch = $this->service->fetchDocument('SOP-AML-2024', 'compliance_officer');
        $this->assertEquals('REDIRECTED_TO_LATEST', $fetch['status']);
        $this->assertEquals('SOP-AML-2026', $fetch['latest_doc_code']);
        $this->assertEquals('2.0', $fetch['version']);

        // 433.2 Record search query relevance
        $log = $this->service->recordSearchQuery('due diligence threshold', 'legal', 5, 0.94);
        $this->assertEquals(0.94, (float) $log->relevance_score);

        // 433.4 Audit clean
        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);
    }

    public function test_acl_unauthorized_access_blocked_edge_case(): void
    {
        // 433.6 Risk: Unauthorized role trying to access restricted legal corpus is blocked
        $this->service->registerDocument(
            docCode: 'CONFIDENTIAL-M-AND-A',
            title: 'Merger Agreement Protocol',
            corpus: 'executive',
            version: '1.0',
            aclAllowedRoles: ['board_director', 'general_counsel']
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Access denied: Role \'guest_user\' lacks permission');

        $this->service->fetchDocument('CONFIDENTIAL-M-AND-A', 'guest_user');
    }
}

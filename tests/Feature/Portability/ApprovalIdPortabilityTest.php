<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

test('approval_id columns in procurement, supplier, and asset tables support UUID strings', function () {
    $tables = [
        'prc_requisitions',
        'prc_tender_bids',
        'prc_po_versions',
        'prc_payment_batches',
        'sup_qualifications',
        'ast_revaluations',
        'ast_disposals',
    ];

    foreach ($tables as $table) {
        expect(Schema::hasTable($table))->toBeTrue("Table {$table} should exist");
        $type = Schema::getColumnType($table, 'approval_id');
        expect($type)
            ->toBeIn(['string', 'varchar'], "Table {$table}.approval_id should be string/varchar to support UUID/ULID approvals, got: {$type}");
    }
});

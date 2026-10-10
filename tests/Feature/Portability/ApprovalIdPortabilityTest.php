<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

test('approval_id columns in procurement, supplier, asset, and contract tables are integer with FK to core_approvals', function () {
    $tables = [
        'prc_requisitions',
        'prc_tender_bids',
        'prc_po_versions',
        'prc_payment_batches',
        'sup_qualifications',
        'ast_revaluations',
        'ast_disposals',
        'ctr_contracts',
    ];

    foreach ($tables as $table) {
        expect(Schema::hasTable($table))->toBeTrue("Table {$table} should exist");
        $type = Schema::getColumnType($table, 'approval_id');
        expect($type)
            ->toBeIn(['integer', 'bigint'], "Table {$table}.approval_id should be integer/bigint referencing core_approvals.id, got: {$type}");

        $fks = collect(DB::select("PRAGMA foreign_key_list({$table})"));
        $hasCoreApprovalsFk = $fks->contains(function (object $fk) {
            return ($fk->table ?? null) === 'core_approvals' && ($fk->from ?? null) === 'approval_id';
        });
        expect($hasCoreApprovalsFk)->toBeTrue("Table {$table} must have foreign key from approval_id to core_approvals.id");
    }
});

test('every seeded approval_id row references an existing core_approvals.id', function () {
    $this->seed();
    $tables = [
        'prc_requisitions',
        'prc_tender_bids',
        'prc_po_versions',
        'prc_payment_batches',
        'sup_qualifications',
        'ast_revaluations',
        'ast_disposals',
        'ctr_contracts',
    ];

    foreach ($tables as $table) {
        $invalid = DB::table($table)
            ->whereNotNull('approval_id')
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('core_approvals')
                    ->whereColumn('core_approvals.id', 'approval_id');
            })
            ->count();
        expect($invalid)->toBe(0, "Table {$table} has seeded approval_id not referencing core_approvals.id");
    }
});

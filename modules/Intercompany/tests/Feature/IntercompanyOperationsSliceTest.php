<?php

declare(strict_types=1);

namespace Modules\Intercompany\tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Modules\Intercompany\Application\Actions\ApplyArmsLengthTransferPricingMarkupAction;
use Modules\Intercompany\Application\Actions\CreateMirroredIntercompanyTransactionAction;
use Modules\Intercompany\Application\Actions\GenerateIntercompanyEliminationJournalAction;
use Modules\Intercompany\Domain\Models\TransferPricingRule;
use Tests\TestCase;

class IntercompanyOperationsSliceTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected TransferPricingRule $rule;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['role' => 'admin']);

        $this->rule = TransferPricingRule::create([
            'rule_code' => 'TP-AUTO-CPM-01',
            'product_category' => 'Automotive Components',
            'tp_method' => 'CPM',
            'min_arms_length_margin_percent' => 6.0,
            'max_arms_length_margin_percent' => 12.0,
            'benchmark_industry_source' => 'OECD_STAT_2026',
            'is_active' => true,
        ]);
    }

    public function test_create_mirrored_intercompany_transaction_success(): void
    {
        $action = app(CreateMirroredIntercompanyTransactionAction::class);

        $tx = $action->execute([
            'selling_entity' => 'PT Manufaktur Otomotif Utama',
            'buying_entity' => 'PT Distribusi Logistik Nusantara',
            'description' => 'Delivery of transmission assembly batch #99',
            'amount_idr' => 450000000,
            'currency' => 'IDR',
        ]);

        $this->assertNotNull($tx->id);
        $this->assertStringStartsWith('IC-', $tx->transaction_code);
        $this->assertSame('matched', $tx->status);
        $this->assertSame(450000000, $tx->amount_idr);
        $this->assertNotEmpty($tx->sales_invoice_ref);
        $this->assertNotEmpty($tx->purchase_bill_ref);
    }

    public function test_create_mirrored_intercompany_transaction_rejects_identical_entities(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $action = app(CreateMirroredIntercompanyTransactionAction::class);

        $action->execute([
            'selling_entity' => 'PT Induk',
            'buying_entity' => 'PT Induk',
            'description' => 'Invalid internal self loop',
            'amount_idr' => 1000000,
        ]);
    }

    public function test_apply_arms_length_transfer_pricing_markup_within_range(): void
    {
        $action = app(ApplyArmsLengthTransferPricingMarkupAction::class);

        // Base cost: 200,000,000 IDR
        // Requested margin: 8.5% (within 6.0% - 12.0%)
        // Markup: round(200,000,000 * 0.085) = 17,000,000 IDR
        // Transfer price: 217,000,000 IDR
        $res = $action->execute($this->rule, 200000000, 8.5);

        $this->assertSame('TP-AUTO-CPM-01', $res['rule_code']);
        $this->assertTrue($res['is_within_arms_length']);
        $this->assertSame(17000000, $res['markup_amount_idr']);
        $this->assertSame(217000000, $res['transfer_price_idr']);
    }

    public function test_apply_arms_length_transfer_pricing_markup_rejects_out_of_range(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $action = app(ApplyArmsLengthTransferPricingMarkupAction::class);

        // Margin 15.0% exceeds max 12.0%
        $action->execute($this->rule, 200000000, 15.0);
    }

    public function test_generate_intercompany_elimination_journal_success(): void
    {
        $action = app(GenerateIntercompanyEliminationJournalAction::class);

        $entry = $action->execute([
            'period' => '2026-10',
            'elimination_type' => 'RECIPROCAL_AR_AP',
            'debit_account' => '2100-AP-INTERCOMPANY',
            'credit_account' => '1100-AR-INTERCOMPANY',
            'amount_idr' => 750000000,
        ]);

        $this->assertNotNull($entry->id);
        $this->assertSame('2026-10', $entry->period);
        $this->assertSame('RECIPROCAL_AR_AP', $entry->elimination_type);
        $this->assertSame(750000000, $entry->amount_idr);
        $this->assertSame('posted', $entry->status);
    }

    public function test_intercompany_consolidation_controller_endpoints(): void
    {
        $this->actingAs($this->user);

        // 1. Index
        $respDash = $this->get('/intercompany/consolidation');
        $respDash->assertOk();

        // 2. Mirror TX API
        $respTx = $this->postJson('/intercompany/consolidation/mirror-tx', [
            'selling_entity' => 'PT Semen Perkasa',
            'buying_entity' => 'PT Konstruksi Mandiri',
            'description' => 'Pengiriman Portland Composite Cement 500 Ton',
            'amount_idr' => 600000000,
        ]);
        $respTx->assertStatus(201);
        $this->assertSame(600000000, $respTx->json('transaction.amount_idr'));

        // 3. Transfer Pricing Markup API
        $respMarkup = $this->postJson("/intercompany/consolidation/markup/{$this->rule->id}", [
            'base_cost_idr' => 100000000,
            'requested_margin_percent' => 10.0,
        ]);
        $respMarkup->assertOk();
        $this->assertSame(110000000, $respMarkup->json('data.transfer_price_idr'));

        // 4. Elimination Journal API
        $respElim = $this->postJson('/intercompany/consolidation/elimination', [
            'period' => '2026-10',
            'elimination_type' => 'REVENUE_EXPENSE',
            'debit_account' => '4100-IC-SALES',
            'credit_account' => '5100-IC-COGS',
            'amount_idr' => 300000000,
        ]);
        $respElim->assertStatus(201);
        $this->assertSame('REVENUE_EXPENSE', $respElim->json('elimination_entry.elimination_type'));
    }
}

<?php

declare(strict_types=1);

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Banking\Domain\Models\LedgerAccount;
use Modules\Logistics\Application\Actions\CreateClaimAction;
use Modules\Logistics\Application\Actions\DecideClaimAction;
use Modules\Logistics\Application\Actions\PayClaimAction;
use Modules\Logistics\Application\Actions\SubmitClaimAction;
use Modules\Logistics\Application\Services\LogisticsLedger;
use Modules\Logistics\Domain\Enums\ShipmentStatus;
use Modules\Logistics\Domain\Exceptions\ClaimException;
use Modules\Logistics\Domain\Models\Claim;
use Modules\Logistics\Domain\Models\Shipment;
use Modules\Logistics\tests\Support\MoneyFlowWorld;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->world = new MoneyFlowWorld;
    $this->shipper = $this->world->shipper();
    $this->creator = $this->world->dispatcher;           // pembuat
    $this->submitter = $this->world->user('dispatcher'); // pengaju
    $this->approver = $this->world->admin;               // penyetuju (logistics_admin)
});

function deliveredInsured(object $t, int $declared = 2_000_000, bool $insured = true): Shipment
{
    $shipment = $t->world->bookPrepaid($t->shipper, insured: $insured, declared: $declared);
    $t->world->deliver($shipment);

    return $shipment->fresh();
}

function claimWalk(object $t, Shipment $shipment, int $claimed, ?int $approved = null): Claim
{
    $claim = app(CreateClaimAction::class)->execute($t->creator, $shipment, 'damage', $claimed, 'Kemasan rusak berat');
    app(SubmitClaimAction::class)->execute($t->submitter, $claim);

    return app(DecideClaimAction::class)->execute($t->approver, $claim, true, $approved, 'Bukti foto valid');
}

test('(a) insured damage claim runs create, submit, approve and pay with the wallet credited', function () {
    $shipment = deliveredInsured($this);
    $walletBefore = (int) $this->shipper->walletAccount('IDR')->fresh()->cached_balance;

    $claim = claimWalk($this, $shipment, 1_500_000, 1_200_000);

    expect($claim->status)->toBe('approved')->and($claim->cap_amount_idr)->toBe(2_000_000)->and($claim->insured)->toBeTrue()
        ->and($claim->claim_number)->toStartWith('CLM-');

    $paid = app(PayClaimAction::class)->execute($this->approver, $claim);

    expect($paid->status)->toBe('paid')
        ->and($paid->paid_amount_idr)->toBe(1_200_000)
        ->and((int) $this->shipper->walletAccount('IDR')->fresh()->cached_balance)->toBe($walletBefore + 1_200_000)
        ->and((int) LedgerAccount::where('code', LogisticsLedger::CLAIMS_EXPENSE)->value('cached_balance'))->toBe(-1_200_000);
});

test('uninsured claims are capped at ten times the freight and delay claims at the freight', function () {
    $shipment = deliveredInsured($this, 0, false);
    $create = app(CreateClaimAction::class);

    expect($create->capFor($shipment, 'damage'))->toBe($shipment->total_amount_idr * 10)
        ->and($create->capFor($shipment, 'delay'))->toBe($shipment->total_amount_idr);

    expect(fn () => $create->execute($this->creator, $shipment, 'damage', $shipment->total_amount_idr * 10 + 1, 'x'))->toThrow(ClaimException::class, 'melebihi batas');
});

test('(b) a paid claim cannot be paid again and a shipment holds only one active claim', function () {
    $shipment = deliveredInsured($this);
    $claim = claimWalk($this, $shipment, 500_000);
    $pay = app(PayClaimAction::class);
    $pay->execute($this->approver, $claim);

    expect(fn () => $pay->execute($this->approver, $claim->fresh()))->toThrow(ClaimException::class, "status 'paid'");
    expect(fn () => app(CreateClaimAction::class)->execute($this->creator, $shipment, 'damage', 100_000, 'lagi'))->toThrow(ClaimException::class, 'sudah memiliki klaim aktif');
    expect(LedgerAccount::where('code', LogisticsLedger::CLAIMS_EXPENSE)->value('cached_balance'))->toEqual(-500_000);

    // Dijamin juga oleh unique index, bukan hanya aplikasi.
    expect(fn () => Claim::create([
        'claim_number' => 'CLM-DUP', 'shipment_id' => $shipment->id, 'claim_type' => 'damage', 'claimed_amount_idr' => 1, 'cap_amount_idr' => 1,
        'description' => 'dup', 'status' => 'draft', 'active_key' => 'shipment:'.$shipment->id, 'created_by' => $this->creator->id,
    ]))->toThrow(UniqueConstraintViolationException::class);
});

test('a rejected claim releases the shipment for a new claim', function () {
    $shipment = deliveredInsured($this);
    $claim = app(CreateClaimAction::class)->execute($this->creator, $shipment, 'damage', 500_000, 'Rusak');
    app(SubmitClaimAction::class)->execute($this->submitter, $claim);
    $rejected = app(DecideClaimAction::class)->execute($this->approver, $claim, false, null, 'Bukti tidak cukup');

    expect($rejected->status)->toBe('rejected')->and($rejected->active_key)->toBeNull();

    $second = app(CreateClaimAction::class)->execute($this->creator, $shipment, 'damage', 400_000, 'Bukti baru');
    expect($second->status)->toBe('draft');
});

test('(c) ledger reconciles after claim payouts', function () {
    claimWalk($this, deliveredInsured($this), 800_000);
    app(PayClaimAction::class)->execute($this->approver, Claim::first());

    $this->artisan('bank:reconcile')->assertSuccessful();
});

test('(d) four eyes rule: creator, submitter and approver must be three different people', function () {
    $claim = app(CreateClaimAction::class)->execute($this->creator, deliveredInsured($this), 'damage', 300_000, 'Rusak');

    expect(fn () => app(SubmitClaimAction::class)->execute($this->creator, $claim))->toThrow(ClaimException::class, 'Aturan 4 mata');
    app(SubmitClaimAction::class)->execute($this->submitter, $claim);

    $decide = app(DecideClaimAction::class);
    expect(fn () => $decide->execute($this->creator, $claim, true, null, 'ok'))->toThrow(ClaimException::class, 'berwenang');

    // Admin logistik yang sekaligus pembuat / pengaju ditolak.
    $adminCreated = app(CreateClaimAction::class)->execute($this->approver, deliveredInsured($this), 'damage', 300_000, 'Rusak');
    app(SubmitClaimAction::class)->execute($this->submitter, $adminCreated);
    expect(fn () => $decide->execute($this->approver, $adminCreated, true, null, 'ok'))->toThrow(ClaimException::class, 'Aturan 4 mata');

    $adminSubmitted = app(CreateClaimAction::class)->execute($this->creator, deliveredInsured($this), 'damage', 300_000, 'Rusak');
    app(SubmitClaimAction::class)->execute($this->approver, $adminSubmitted);
    expect(fn () => $decide->execute($this->approver, $adminSubmitted, true, null, 'ok'))->toThrow(ClaimException::class, 'Aturan 4 mata');

    $other = $this->world->user('logistics_admin');
    expect($decide->execute($other, $claim, true, null, 'Disetujui')->status)->toBe('approved');
});

test('(d) amounts, states, eligibility, window and insured basis are validated', function () {
    $create = app(CreateClaimAction::class);
    $shipment = deliveredInsured($this);

    expect(fn () => $create->execute($this->creator, $shipment, 'damage', 2_000_001, 'x'))->toThrow(ClaimException::class, 'melebihi batas')
        ->and(fn () => $create->execute($this->creator, $shipment, 'loss', 1_000, 'x'))->toThrow(ClaimException::class, 'tidak memenuhi syarat')
        ->and(fn () => $create->execute($this->creator, $shipment, 'damage', 0, 'x'))->toThrow(ClaimException::class);

    $claim = $create->execute($this->creator, $shipment, 'damage', 1_000_000, 'ok');
    expect(fn () => app(PayClaimAction::class)->execute($this->approver, $claim))->toThrow(ClaimException::class, "status 'draft'");
    app(SubmitClaimAction::class)->execute($this->submitter, $claim);
    expect(fn () => app(DecideClaimAction::class)->execute($this->world->user('logistics_admin'), $claim, true, 1_000_001, 'x'))->toThrow(ClaimException::class, 'melebihi batas');

    $noBasis = deliveredInsured($this, 0, true);
    expect(fn () => $create->execute($this->creator, $noBasis, 'damage', 1_000, 'x'))->toThrow(ClaimException::class, 'nilai barang');

    $old = deliveredInsured($this);
    $old->update(['delivered_at' => now()->subDays(15)]);
    expect(fn () => $create->execute($this->creator, $old->fresh(), 'damage', 1_000, 'x'))->toThrow(ClaimException::class, 'Masa pengajuan');

    $lost = $this->world->bookPrepaid($this->shipper, insured: true, declared: 500_000);
    $lost->update(['status' => ShipmentStatus::Lost]);
    expect($create->execute($this->creator, $lost->fresh(), 'loss', 500_000, 'Hilang')->claim_type)->toBe('loss');
});

test('(e) shippers create claims only for their own shipments and cannot submit, decide or pay', function () {
    $mine = deliveredInsured($this);
    $other = $this->world->bookPrepaid($this->world->shipper());
    $this->world->deliver($other);
    $create = app(CreateClaimAction::class);

    expect(fn () => $create->execute($this->shipper, $other->fresh(), 'damage', 1_000, 'x'))->toThrow(ClaimException::class, 'berwenang');

    $claim = $create->execute($this->shipper, $mine, 'damage', 400_000, 'Rusak');
    expect(fn () => app(SubmitClaimAction::class)->execute($this->shipper, $claim))->toThrow(ClaimException::class, 'berwenang');

    app(SubmitClaimAction::class)->execute($this->submitter, $claim);
    expect(fn () => app(DecideClaimAction::class)->execute($this->creator, $claim, true, null, 'x'))->toThrow(ClaimException::class, 'berwenang');

    app(DecideClaimAction::class)->execute($this->approver, $claim, true, null, 'ok');
    expect(fn () => app(PayClaimAction::class)->execute($this->submitter, $claim->fresh()))->toThrow(ClaimException::class, 'berwenang');
});

test('(e) http: claim pages are scoped per role and the full workflow works through the ui routes', function () {
    $mine = deliveredInsured($this);
    $stranger = $this->world->shipper();

    $this->actingAs($this->shipper)->post(route('logistics.claims.store'), [
        'tracking_number' => $mine->tracking_number, 'claim_type' => 'damage', 'claimed_amount_idr' => 300_000, 'description' => 'Penyok',
    ])->assertSessionHas('success');
    $claim = Claim::firstOrFail();

    $this->actingAs($this->shipper)->get(route('logistics.claims.index'))->assertOk()->assertSee($claim->claim_number);
    $this->actingAs($stranger)->get(route('logistics.claims.index'))->assertOk()->assertDontSee($claim->claim_number);
    $this->actingAs($stranger)->post(route('logistics.claims.store'), [
        'tracking_number' => $mine->tracking_number, 'claim_type' => 'damage', 'claimed_amount_idr' => 1, 'description' => 'x',
    ])->assertSessionHas('error');
    foreach (['hub_operator', 'driver'] as $role) {
        $this->actingAs($this->world->user($role))->get(route('logistics.claims.index'))->assertForbidden();
    }

    $this->actingAs($this->submitter)->post(route('logistics.claims.submit', $claim->id))->assertSessionHas('success');
    $this->actingAs($this->submitter)->post(route('logistics.claims.decide', $claim->id), ['decision' => 'approve', 'notes' => 'x'])->assertSessionHas('error');
    $this->actingAs($this->approver)->post(route('logistics.claims.decide', $claim->id), ['decision' => 'approve', 'notes' => 'Valid'])->assertSessionHas('success');
    $this->actingAs($this->approver)->post(route('logistics.claims.pay', $claim->id))->assertSessionHas('success');

    expect($claim->fresh()->status)->toBe('paid');
});

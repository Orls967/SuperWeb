<?php

declare(strict_types=1);

namespace Modules\Payment\tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\Payment\Domain\Enums\PaymentIntentStatus;
use Modules\Payment\Domain\Models\PaymentIntent;
use Tests\TestCase;

class PaymentIntentInspectorHttpTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get(route('payment.inspector.index'));
        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_view_payment_intent_inspector(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $intent1 = PaymentIntent::create([
            'uuid' => (string) Str::uuid(),
            'payer_id' => $user->id,
            'payable_type' => 'App\Models\Order',
            'payable_id' => 1001,
            'amount' => '1500000',
            'currency' => 'IDR',
            'status' => PaymentIntentStatus::CAPTURED,
            'idempotency_key' => 'idemp-cap-1001',
        ]);

        $intent2 = PaymentIntent::create([
            'uuid' => (string) Str::uuid(),
            'payer_id' => $user->id,
            'payable_type' => 'App\Models\Order',
            'payable_id' => 1002,
            'amount' => '2500000',
            'currency' => 'IDR',
            'status' => PaymentIntentStatus::HELD,
            'idempotency_key' => 'idemp-held-1002',
        ]);

        $response = $this->actingAs($user)->get(route('payment.inspector.index'));

        $response->assertOk();
        $response->assertSeeText('Payment Intent Inspector');
        $response->assertSeeText('1,500,000 IDR');
        $response->assertSeeText('2,500,000 IDR');
        $response->assertSeeText('captured');
        $response->assertSeeText('held');
    }

    public function test_filter_intents_by_status(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $intentCaptured = PaymentIntent::create([
            'uuid' => (string) Str::uuid(),
            'payer_id' => $user->id,
            'payable_type' => 'App\Models\Order',
            'payable_id' => 2001,
            'amount' => '500000',
            'currency' => 'IDR',
            'status' => PaymentIntentStatus::CAPTURED,
            'idempotency_key' => 'idemp-cap-2001',
        ]);

        $intentFailed = PaymentIntent::create([
            'uuid' => (string) Str::uuid(),
            'payer_id' => $user->id,
            'payable_type' => 'App\Models\Order',
            'payable_id' => 2002,
            'amount' => '850000',
            'currency' => 'IDR',
            'status' => PaymentIntentStatus::FAILED,
            'failure_reason' => 'Insufficient funds on credit line',
            'idempotency_key' => 'idemp-fail-2002',
        ]);

        $response = $this->actingAs($user)->get(route('payment.inspector.index', [
            'status' => PaymentIntentStatus::FAILED->value,
        ]));

        $response->assertOk();
        $response->assertSeeText('850,000 IDR');
        $response->assertSeeText('failed');
        $response->assertDontSeeText('500,000 IDR');
    }

    public function test_inspect_single_payment_intent_detail(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $uuid = (string) Str::uuid();
        $intent = PaymentIntent::create([
            'uuid' => $uuid,
            'payer_id' => $user->id,
            'payable_type' => 'App\Models\Order',
            'payable_id' => 3001,
            'amount' => '3200000',
            'currency' => 'IDR',
            'status' => PaymentIntentStatus::CAPTURED,
            'idempotency_key' => 'idemp-show-3001',
        ]);

        $response = $this->actingAs($user)->get(route('payment.inspector.show', $uuid));

        $response->assertOk();
        $response->assertSeeText('Payment Intent #');
        $response->assertSeeText('3,200,000 IDR');
        $response->assertSeeText('idemp-show-3001');
    }
}

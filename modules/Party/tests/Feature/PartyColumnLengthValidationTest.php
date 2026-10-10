<?php

declare(strict_types=1);

namespace Modules\Party\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PartyColumnLengthValidationTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminUser = User::factory()->create([
            'role' => 'admin',
            'email_verified_at' => now(),
        ]);
    }

    public function test_party_role_validation_rejects_51_chars_with_422(): void
    {
        $response = $this->actingAs($this->adminUser)->postJson(route('party.store'), [
            'type' => 'company',
            'name' => 'PT Validasi Role Panjang',
            'role' => str_repeat('a', 51), // 51 chars > 50
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['role']);
    }

    public function test_party_role_validation_accepts_valid_enum_role(): void
    {
        $response = $this->actingAs($this->adminUser)->post(route('party.store'), [
            'type' => 'company',
            'name' => 'PT Validasi Role Pas',
            'role' => 'distributor', // valid enum (11 chars <= 50)
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();
    }

    public function test_party_scope_type_validation_rejects_51_chars_with_422(): void
    {
        $response = $this->actingAs($this->adminUser)->postJson(route('party.store'), [
            'type' => 'company',
            'name' => 'PT Validasi Scope Type Panjang',
            'scope_type' => str_repeat('b', 51), // 51 chars > 50
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['scope_type']);
    }

    public function test_party_scope_type_validation_accepts_50_chars(): void
    {
        $response = $this->actingAs($this->adminUser)->post(route('party.store'), [
            'type' => 'company',
            'name' => 'PT Validasi Scope Type Pas',
            'scope_type' => str_repeat('b', 50), // 50 chars == max:50
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();
    }

    public function test_party_scope_id_validation_rejects_101_chars_with_422(): void
    {
        $response = $this->actingAs($this->adminUser)->postJson(route('party.store'), [
            'type' => 'company',
            'name' => 'PT Validasi Scope ID Panjang',
            'scope_id' => str_repeat('c', 101), // 101 chars > 100
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['scope_id']);
    }

    public function test_party_scope_id_validation_accepts_100_chars(): void
    {
        $response = $this->actingAs($this->adminUser)->post(route('party.store'), [
            'type' => 'company',
            'name' => 'PT Validasi Scope ID Pas',
            'scope_id' => str_repeat('c', 100), // 100 chars == max:100
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();
    }
}

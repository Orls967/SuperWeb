<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\LedgerAccountsSeeder;
use Modules\Syariah\database\seeders\SyariahRbacSeeder;

beforeEach(function (): void {
    $this->artisan('migrate:fresh');
    $this->seed(LedgerAccountsSeeder::class);
    $this->seed(SyariahRbacSeeder::class);
});

test('syariah murabahah http endpoint renders correctly for authorized roles', function (): void {
    $officer = User::factory()->create();
    $officer->assignRole('syariah_officer');

    $response = $this->actingAs($officer)->get('/syariah/murabahah');
    $response->assertOk();
    $response->assertSee('Meja Operasional Akad Murabahah');
});

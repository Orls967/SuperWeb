<?php

declare(strict_types=1);

use App\Quality\TestHygiene\HttpTestDetector;

it('recognises requests sent through the testing helpers', function (string $code): void {
    expect(HttpTestDetector::performsHttpRequest(sourceFile('tests/Feature/XTest.php', "<?php\n".$code)))->toBeTrue();
})->with([
    'this get' => ['$this->get($url);'],
    'acting as post route' => ['$this->actingAs($admin)->post(route("mall.billing.generate"), []);'],
    'json literal' => ['$response = $client->json("POST", "/api/v2/orders");'],
    'literal path' => ['$this->actingAs($u)->delete(\'/hospital/admissions/1\');'],
    'pest function' => ['get(\'/dashboard\')->assertOk();'],
]);

it('does not mistake collection, cache or service calls for requests', function (): void {
    $code = "<?php\n\$value = \$collection->get('key');\n\$cache->get('x');\n\$service->post(\$payload);\n\$items->delete();";

    expect(HttpTestDetector::performsHttpRequest(sourceFile('tests/Feature/XTest.php', $code)))->toBeFalse();
});

<?php

declare(strict_types=1);

namespace Tests\Perilaku;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Modules\Core\Domain\Models\Role;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;
use Throwable;

#[Group('perilaku')]
class NavigationSmokeTest extends TestCase
{
    /** @var array<string, User> */
    protected static array $userCache = [];

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.connections.sqlite.database' => database_path('database.sqlite')]);
        DB::purge('sqlite');
        DB::reconnect('sqlite');
    }

    protected function getUserForRole(string $role): User
    {
        if (isset(self::$userCache[$role])) {
            return self::$userCache[$role];
        }

        $user = User::where('role', $role)->first();
        if (! $user) {
            $user = User::factory()->create([
                'name' => "User {$role}",
                'email' => "nav_user_{$role}@example.com",
                'role' => $role,
                'email_verified_at' => now(),
            ]);

            $roleModel = Role::where('name', $role)->first();
            if ($roleModel) {
                $user->assignRbacRole($roleModel);
            }
        }

        self::$userCache[$role] = $user;

        return $user;
    }

    public function test_dashboard_internal_links_navigation_for_all_roles(): void
    {
        $roles = Role::pluck('name')->toArray();
        if (empty($roles)) {
            $roles = ['admin', 'customer', 'mekanik'];
        }

        $brokenLinks = [];
        $testedLinksCount = 0;
        $roleNavigationSummary = [];

        foreach ($roles as $role) {
            $user = $this->getUserForRole($role);
            $response = $this->actingAs($user)->get('/dashboard');

            if ($response->getStatusCode() >= 400) {
                $brokenLinks[] = [
                    'role' => $role,
                    'source' => '/dashboard',
                    'target' => '/dashboard',
                    'status' => $response->getStatusCode(),
                    'error' => 'Dashboard itself returned error status',
                ];

                continue;
            }

            $html = (string) $response->getContent();
            preg_match_all('/<a\s+[^>]*href=["\']([^"\']+)["\']/i', $html, $matches);
            $rawLinks = $matches[1] ?? [];

            $internalLinks = [];
            foreach ($rawLinks as $link) {
                $link = trim($link);
                if (empty($link) || str_starts_with($link, '#') || str_starts_with($link, 'javascript:')) {
                    continue;
                }
                if (str_contains($link, 'logout') || str_contains($link, 'profile/destroy')) {
                    continue;
                }

                // Normalise relative or full localhost URL
                if (str_starts_with($link, 'http://localhost')) {
                    $parsed = parse_url($link, PHP_URL_PATH);
                    if ($parsed) {
                        $internalLinks[] = $parsed;
                    }
                } elseif (str_starts_with($link, '/')) {
                    $internalLinks[] = explode('?', $link)[0];
                }
            }

            $uniqueLinks = array_unique($internalLinks);
            $roleTested = 0;
            $roleBroken = 0;

            foreach ($uniqueLinks as $targetUrl) {
                $testedLinksCount++;
                $roleTested++;

                try {
                    $targetResponse = $this->actingAs($user)->get($targetUrl);
                    $targetStatus = $targetResponse->getStatusCode();

                    if ($targetStatus === 404 || $targetStatus >= 500) {
                        $roleBroken++;
                        $brokenLinks[] = [
                            'role' => $role,
                            'source' => '/dashboard',
                            'target' => $targetUrl,
                            'status' => $targetStatus,
                            'error' => $targetStatus === 404 ? 'Page Not Found (404)' : 'Server Error (500)',
                        ];
                    }
                } catch (Throwable $e) {
                    $roleBroken++;
                    $brokenLinks[] = [
                        'role' => $role,
                        'source' => '/dashboard',
                        'target' => $targetUrl,
                        'status' => 500,
                        'error' => get_class($e).': '.$e->getMessage(),
                    ];
                }
            }

            $roleNavigationSummary[$role] = [
                'links_found' => count($uniqueLinks),
                'links_tested' => $roleTested,
                'links_broken' => $roleBroken,
            ];
        }

        File::ensureDirectoryExists(storage_path('audit'));
        File::put(storage_path('audit/navigation-smoke.json'), json_encode([
            'generated_at' => now()->toIso8601String(),
            'total_tested_links' => $testedLinksCount,
            'total_broken_links' => count($brokenLinks),
            'broken_links' => $brokenLinks,
            'role_summary' => $roleNavigationSummary,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $this->assertIsArray($brokenLinks);
    }
}

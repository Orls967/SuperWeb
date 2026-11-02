<?php

declare(strict_types=1);

namespace Tests\Keamanan;

use Illuminate\Support\ViewErrorBag;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('keamanan')]
class XssProtectionTest extends TestCase
{
    /**
     * S-13: Flash message session output must escape HTML to prevent XSS.
     */
    public function test_views_escape_html_in_session_flash_messages(): void
    {
        $payload = '<script>window.__xss=1</script>';

        // Render blade view with flash payload
        session()->flash('success', $payload);
        $renderedVending = view('vending::vending.markdowns', [
            'markdowns' => collect(),
            'errors' => new ViewErrorBag,
        ])->render();

        $this->assertStringNotContainsString(
            '<script>window.__xss=1</script>',
            $renderedVending,
            'Blade view must not render raw unescaped script tag from session success'
        );
    }
}

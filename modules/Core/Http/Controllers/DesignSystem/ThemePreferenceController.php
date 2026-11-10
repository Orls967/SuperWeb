<?php

declare(strict_types=1);

namespace Modules\Core\Http\Controllers\DesignSystem;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Modules\Core\Domain\Models\DsUserPref;
use Modules\Core\Http\Requests\DesignSystem\UpdateThemePreferenceRequest;

final class ThemePreferenceController extends Controller
{
    public function update(UpdateThemePreferenceRequest $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validated();
        $user = $request->user();

        if ($user !== null) {
            DsUserPref::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'theme' => $validated['theme'],
                    'density' => $validated['density'] ?? 'comfortable',
                    'font_scale' => $validated['font_scale'] ?? 100,
                    'reduced_motion' => (bool) ($validated['reduced_motion'] ?? false),
                    'high_contrast' => (bool) ($validated['high_contrast'] ?? ($validated['theme'] === 'hc')),
                    'updated_at' => now(),
                ]
            );
        }

        // Set session
        session(['ds_theme' => $validated['theme']]);

        if ($request->wantsJson()) {
            return response()->json([
                'status' => 'success',
                'theme' => $validated['theme'],
                'message' => 'Preferensi tema berhasil disimpan.',
            ]);
        }

        return back()->with('status', 'Preferensi tema berhasil diperbarui.');
    }
}


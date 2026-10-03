<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Redirect;

class ApiTokenController extends Controller
{
    /**
     * Terbitkan personal access token baru (Sanctum).
     *
     * Plaintext token hanya dikembalikan sekali lewat flash session.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'abilities' => ['required', 'string', 'max:200'],
            'expires_at' => ['nullable', 'date', 'after:now'],
        ]);

        $abilities = collect(explode(',', $validated['abilities']))
            ->map(fn (string $ability) => trim($ability))
            ->filter()
            ->values()
            ->all();

        if ($abilities === []) {
            $abilities = ['*'];
        }

        $expiresAt = isset($validated['expires_at'])
            ? Carbon::parse($validated['expires_at'])
            : null;

        $plainTextToken = $request->user()
            ->createToken($validated['name'], $abilities, $expiresAt)
            ->plainTextToken;

        return Redirect::route('profile.edit', ['token_created' => $plainTextToken]);
    }

    /**
     * Cabut satu token atau seluruh token milik user.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'token_id' => ['nullable', 'integer'],
        ]);

        $tokens = $request->user()->tokens();

        if (isset($validated['token_id'])) {
            $tokens->whereKey($validated['token_id'])->firstOrFail();
            $tokens->whereKey($validated['token_id'])->delete();
        } else {
            $tokens->delete();
        }

        return Redirect::route('profile.edit')->with('status', 'token-revoked');
    }
}

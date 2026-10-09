<section class="space-y-6">
    <header>
        <h2 class="text-lg font-medium text-gray-900">
            {{ __('API Tokens') }}
        </h2>

        <p class="mt-1 text-sm text-gray-600">
            Token dipakai untuk API v1 (`Authorization: Bearer <token>`). Simpan plaintext token segera setelah dibuat — token hanya ditampilkan satu kali.
        </p>
    </header>

    @if (request()->query('token_created'))
        <div class="rounded-lg bg-green-50 p-4 border border-green-200">
            <p class="text-sm font-medium text-green-800">{{ __('Token berhasil dibuat. Salin sekarang, token tidak akan ditampilkan lagi.') }}</p>
            <code class="mt-2 block break-all rounded bg-white p-2 text-xs text-gray-800 border border-green-200">{{ request()->query('token_created') }}</code>
        </div>
    @endif

    @if (session('status') === 'token-revoked')
        <div class="rounded-lg bg-green-50 p-4 border border-green-200">
            <p class="text-sm font-medium text-green-800">{{ __('Token dicabut.') }}</p>
        </div>
    @endif

    @if ($tokens->isNotEmpty())
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-3 py-2 text-left font-medium text-gray-600">{{ __('Name') }}</th>
                        <th class="px-3 py-2 text-left font-medium text-gray-600">{{ __('Abilities') }}</th>
                        <th class="px-3 py-2 text-left font-medium text-gray-600">{{ __('Last used') }}</th>
                        <th class="px-3 py-2 text-left font-medium text-gray-600">{{ __('Expires') }}</th>
                        <th class="px-3 py-2"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @foreach ($tokens as $token)
                        <tr>
                            <td class="px-3 py-2 text-gray-900">{{ $token->name }}</td>
                            <td class="px-3 py-2 text-gray-600">{{ implode(', ', $token->abilities) }}</td>
                            <td class="px-3 py-2 text-gray-600">{{ $token->last_used_at?->diffForHumans() ?? __('Never') }}</td>
                            <td class="px-3 py-2 text-gray-600">
                                {{ $token->expires_at?->format('Y-m-d') ?? __('No expiry') }}
                                @if ($token->expires_at?->isPast())
                                    <span class="ml-1 rounded bg-red-100 px-1.5 py-0.5 text-xs text-red-700">{{ __('Expired') }}</span>
                                @endif
                            </td>
                            <td class="px-3 py-2 text-right">
                                <form method="post" action="{{ route('profile.api-tokens.destroy') }}" class="inline"
                                      onsubmit="return confirm('{{ __('Revoke this token?') }}')">
                                    @csrf
                                    @method('delete')
                                    <input type="hidden" name="token_id" value="{{ $token->id }}">
                                    <button type="submit" class="text-sm text-red-600 hover:text-red-800 hover:underline">
                                        {{ __('Revoke') }}
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <form method="post" action="{{ route('profile.api-tokens.destroy') }}">
            @csrf
            @method('delete')
            <x-danger-button>{{ __('Revoke all tokens') }}</x-danger-button>
        </form>
    @endif

    <form method="post" action="{{ route('profile.api-tokens.store') }}" class="mt-6 space-y-4">
        @csrf

        <div>
            <x-input-label for="api_token_name" :value="__('Token name')" />
            <x-text-input id="api_token_name" name="name" type="text" class="mt-1 block w-full"
                          :value="old('name')" placeholder="integrasi-erp" required />
            <x-input-error :messages="$errors->get('name')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="api_token_abilities" :value="__('Abilities (comma separated)')" />
            <x-text-input id="api_token_abilities" name="abilities" type="text" class="mt-1 block w-full"
                          :value="old('abilities', 'shipment:read')" placeholder="shipment:read, quote:create" required />
            <p class="mt-1 text-xs text-gray-500">
                {{ __('Available: quote:create, shipment:create, shipment:read, or * for full access.') }}
            </p>
            <x-input-error :messages="$errors->get('abilities')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="api_token_expires_at" :value="__('Expiration (optional)')" />
            <x-text-input id="api_token_expires_at" name="expires_at" type="date" class="mt-1 block w-full"
                          :value="old('expires_at')" min="{{ now()->addDay()->toDateString() }}" />
            <p class="mt-1 text-xs text-gray-500">{{ __('Leave empty for a token that never expires.') }}</p>
            <x-input-error :messages="$errors->get('expires_at')" class="mt-2" />
        </div>

        <div class="flex items-center gap-4">
            <x-primary-button>{{ __('Create token') }}</x-primary-button>
        </div>
    </form>
</section>

<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('B2B Integrasi API v2, Webhook & Pertukaran Data EDI') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <!-- Webhook Subscriptions & Deliveries -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="p-6 bg-white shadow sm:rounded-lg">
                    <h3 class="text-lg font-medium text-gray-900 mb-3">Langganan Webhook B2B</h3>
                    <div class="space-y-3 text-sm">
                        @forelse($subscriptions as $sub)
                            <div class="border rounded p-3">
                                <div class="flex justify-between items-center">
                                    <span class="font-bold text-gray-800">{{ $sub->event_type }}</span>
                                    <span class="px-2 py-0.5 text-xs font-semibold rounded bg-green-100 text-green-800">Aktif</span>
                                </div>
                                <div class="text-xs text-gray-500 mt-1">Mitra: {{ $sub->partner_id }}</div>
                                <div class="text-xs font-mono text-gray-600 mt-1 truncate">{{ $sub->target_url }}</div>
                            </div>
                        @empty
                            <p class="text-sm text-gray-500">Belum ada webhook terdaftar.</p>
                        @endforelse
                    </div>
                </div>

                <div class="p-6 bg-white shadow sm:rounded-lg">
                    <h3 class="text-lg font-medium text-gray-900 mb-3">Pesan EDI (850/855/856/810)</h3>
                    <div class="space-y-3 text-sm">
                        @forelse($ediMessages as $edi)
                            <div class="border rounded p-3">
                                <div class="flex justify-between items-center">
                                    <span class="font-bold text-gray-800">{{ $edi->control_number }} (Set {{ $edi->transaction_set }})</span>
                                    <span class="px-2 py-0.5 text-xs font-semibold rounded bg-blue-100 text-blue-800">{{ $edi->functional_status }}</span>
                                </div>
                                <div class="text-xs text-gray-500 mt-1">{{ $edi->sender_id }} &rarr; {{ $edi->receiver_id }} ({{ $edi->edi_standard }})</div>
                            </div>
                        @empty
                            <p class="text-sm text-gray-500">Belum ada transmisi pesan EDI.</p>
                        @endforelse
                    </div>
                </div>
            </div>

            <!-- API Clients -->
            <div class="p-6 bg-white shadow sm:rounded-lg">
                <h3 class="text-lg font-medium text-gray-900 mb-3">Klien API B2B Terdaftar</h3>
                <div class="space-y-3 text-sm">
                    @forelse($clients as $cli)
                        <div class="border rounded p-3 flex justify-between items-center">
                            <div>
                                <span class="font-bold text-gray-800">{{ $cli->client_name }}</span>
                                <span class="ml-2 px-2 py-0.5 text-xs font-semibold rounded bg-purple-100 text-purple-800">Tier {{ $cli->tier }}</span>
                                <div class="text-xs text-gray-500 mt-1">ID: {{ $cli->client_id }}</div>
                            </div>
                            <div class="text-right">
                                <div class="font-mono text-xs font-bold text-gray-700">Limit: {{ number_format($cli->rate_limit_per_minute) }} req/menit</div>
                                <span class="text-xs text-green-600 font-semibold">&bull; Online</span>
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-gray-500">Belum ada kredensial API B2B diterbitkan.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</x-app-layout>

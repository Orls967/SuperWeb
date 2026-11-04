<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Sertifikat CBAM per Kontainer — Fase 83</h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto px-3 sm:px-6 lg:px-8 space-y-4">
            @if (session('success'))<div class="rounded-md bg-green-50 p-4 text-green-800 text-sm">{{ session('success') }}</div>@endif
            @if ($errors->any())<div class="rounded-md bg-red-50 p-4 text-red-800 text-sm"><ul class="list-disc pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

            <div class="rounded-lg bg-amber-50 px-4 py-3 text-xs sm:text-sm text-amber-800">
                <strong>Simulasi S2:</strong> emisi dari data terverifikasi (K7, ESG); harga karbon &amp; kurs EUR/IDR adalah parameter berversi yang belum dikonfirmasi pemilik untuk produksi.
            </div>

            <div class="grid grid-cols-3 gap-3 text-center">
                <div class="rounded-lg bg-white p-3 shadow"><div class="text-xs text-gray-500">Sertifikat</div><div class="text-lg font-bold">{{ number_format($summary['total']) }}</div></div>
                <div class="rounded-lg bg-white p-3 shadow"><div class="text-xs text-gray-500">tCO2e</div><div class="text-lg font-bold">{{ number_format($summary['milli_tco2e'] / 1000, 3) }}</div></div>
                <div class="rounded-lg bg-white p-3 shadow"><div class="text-xs text-gray-500">Bea (IDR)</div><div class="text-lg font-bold">Rp {{ number_format($summary['levy_idr']) }}</div></div>
            </div>

            <form method="POST" action="{{ route('trade.cbam.declare') }}" class="rounded-lg bg-white p-4 shadow grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @csrf
                <h3 class="font-semibold text-gray-800 sm:col-span-2 lg:col-span-3">Terbitkan sertifikat</h3>
                <input name="container_number" required maxlength="32" placeholder="No. kontainer" class="rounded border-gray-300 text-sm" value="{{ old('container_number') }}">
                <input name="product_code" required maxlength="32" placeholder="Kode produk (mis. STEEL)" class="rounded border-gray-300 text-sm" value="{{ old('product_code') }}">
                <input name="origin_country" required maxlength="2" placeholder="Negara asal (ISO-2)" class="rounded border-gray-300 text-sm" value="{{ old('origin_country') }}">
                <input name="net_mass_kg" required type="number" min="1" placeholder="Massa bersih (kg)" class="rounded border-gray-300 text-sm" value="{{ old('net_mass_kg') }}">
                <input name="idempotency_key" required maxlength="64" placeholder="Idempotency-Key" class="rounded border-gray-300 text-sm" value="{{ old('idempotency_key') }}">
                <button class="rounded bg-teal-600 px-4 py-2 text-sm font-semibold text-white">Terbitkan</button>
            </form>

            <div class="rounded-lg bg-white shadow overflow-x-auto">
                <table class="min-w-full text-xs sm:text-sm">
                    <thead class="bg-gray-50 text-left text-gray-600"><tr><th class="px-3 py-2">Nomor</th><th class="px-3 py-2">Kontainer</th><th class="px-3 py-2">Produk</th><th class="px-3 py-2">tCO2e</th><th class="px-3 py-2">Bea (IDR)</th><th class="px-3 py-2">Label</th></tr></thead>
                    <tbody>
                    @forelse ($declarations as $d)
                        <tr class="border-t"><td class="px-3 py-2 font-mono">{{ $d->certificate_number }}</td><td class="px-3 py-2">{{ $d->container_number }}</td><td class="px-3 py-2">{{ $d->product_code }}/{{ $d->origin_country }}</td><td class="px-3 py-2">{{ $d->embeddedTco2e() }}</td><td class="px-3 py-2">Rp {{ number_format($d->levy_idr) }}</td><td class="px-3 py-2">{{ $d->simulation_label }}</td></tr>
                    @empty
                        <tr><td colspan="6" class="px-3 py-6 text-center text-gray-500">Belum ada sertifikat.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>

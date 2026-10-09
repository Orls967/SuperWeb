<x-app-layout>
    <div class="py-6 max-w-7xl mx-auto px-4">
        <h1 class="text-2xl font-bold mb-2">{{ $partner->name }} ({{ $partner->code }})</h1>
        <p class="text-sm text-gray-500 mb-6">Status: {{ $partner->status }} | Jenis: {{ $partner->kind }}</p>
        <div class="bg-white rounded shadow p-4">
            <h2 class="text-lg font-semibold mb-2">Ringkasan Kemitraan</h2>
            <p class="text-gray-600">Catatan: {{ $partner->notes ?? '-' }}</p>
        </div>
    </div>
</x-app-layout>

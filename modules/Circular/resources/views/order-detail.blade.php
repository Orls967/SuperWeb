<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Detail Pesanan Circular Marketplace') }} — {{ $order->order_number }}
        </h2>
    </x-slot>

    <div class="py-8 max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <div class="bg-white shadow sm:rounded-lg p-6 space-y-3 text-sm">
            <div><strong>Nomor Pesanan:</strong> {{ $order->order_number }}</div>
            <div><strong>Material / Grade:</strong> {{ $order->material_code }} ({{ $order->grade_code }})</div>
            <div><strong>Kuantitas Dipesan:</strong> {{ number_format($order->ordered_grams) }} g</div>
            <div><strong>Kuantitas Diterima Faktual:</strong> {{ $order->accepted_grams !== null ? number_format($order->accepted_grams).' g' : 'Menunggu timbangan pabrik' }}</div>
            <div><strong>Escrow Ditahan:</strong> Rp {{ number_format($order->escrow_amount_idr) }}</div>
            <div><strong>Refund Susut:</strong> Rp {{ number_format($order->refund_amount_idr) }}</div>
            <div><strong>Status:</strong> {{ $order->status }}</div>
        </div>
    </div>
</x-app-layout>


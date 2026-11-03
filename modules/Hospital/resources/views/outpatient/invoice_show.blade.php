@extends('layouts.app')

@section('content')
<div class="py-12">
    <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <div class="p-6 bg-gray-800 shadow sm:rounded-lg">
            <div class="flex justify-between items-center mb-6">
                <div>
                    <h2 class="text-xl font-semibold text-white">Rincian Tagihan Pasien</h2>
                    <p class="mt-1 text-sm text-gray-400">No. Tagihan: <span class="font-mono text-white font-bold">{{ $invoice->invoice_code }}</span></p>
                </div>
                <a href="{{ url()->previous() }}" class="text-gray-400 hover:text-white text-sm">← Kembali</a>
            </div>

            <div class="p-6 bg-gray-900/60 rounded border border-gray-700 space-y-4 text-sm">
                <div class="flex justify-between pb-3 border-b border-gray-800">
                    <span class="text-gray-400">Pasien</span>
                    <span class="text-white font-semibold">{{ $invoice->encounter?->patient?->name }}</span>
                </div>
                <div class="flex justify-between pb-3 border-b border-gray-800">
                    <span class="text-gray-400">Jasa Pemeriksaan Dokter</span>
                    <span class="text-white">Rp {{ number_format($invoice->doctor_fee_idr, 0, ',', '.') }}</span>
                </div>
                <div class="flex justify-between pb-3 border-b border-gray-800">
                    <span class="text-gray-400">Biaya Obat & Farmasi</span>
                    <span class="text-white">Rp {{ number_format($invoice->medication_fee_idr, 0, ',', '.') }}</span>
                </div>
                <div class="flex justify-between pb-3 border-b border-gray-800 text-base font-bold">
                    <span class="text-gray-300">Total Tagihan</span>
                    <span class="text-emerald-400">Rp {{ number_format($invoice->total_amount_idr, 0, ',', '.') }}</span>
                </div>
                <div class="flex justify-between items-center pt-2">
                    <span class="text-gray-400">Status Pembayaran</span>
                    <span class="px-3 py-1 rounded-full text-xs font-semibold {{ $invoice->status === 'PAID' ? 'bg-emerald-900/60 text-emerald-300 border border-emerald-600' : 'bg-amber-900/60 text-amber-300 border border-amber-600' }}">
                        {{ $invoice->status }}
                    </span>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

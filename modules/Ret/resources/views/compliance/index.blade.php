@extends('layouts.app')

@section('content')
    <x-enterprise.module-dashboard module="Ret" />

<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <div class="p-4 sm:p-8 bg-gray-800 shadow sm:rounded-lg">
            <h2 class="text-xl font-semibold text-white">Shelf Planogram Compliance Audit</h2>
            <p class="mt-1 text-sm text-gray-400">Pemeriksaan Kepatuhan Penataan Display Rak Ritel, Facings & Deteksi Out of Stock</p>

            @if(session('success'))
                <div class="mt-4 p-4 bg-emerald-900/50 border border-emerald-500 rounded text-emerald-200 text-sm">
                    {{ session('success') }}
                </div>
            @endif

            <div class="mt-6 bg-gray-700/50 p-6 rounded-lg max-w-xl">
                <h3 class="text-md font-medium text-white mb-4">Input Hasil Pindaian Rak Toko</h3>
                <form action="{{ route('ret.compliance.audit') }}" method="POST" class="space-y-4">
                    @csrf
                    <div>
                        <label class="block text-xs text-gray-300 mb-1">Nomor/Kode Rak</label>
                        <input type="text" name="shelf_id" value="SHELF-BEVERAGE-01" class="w-full bg-gray-800 border border-gray-600 rounded p-2 text-white text-sm">
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs text-gray-300 mb-1">Target Facings Planogram</label>
                            <input type="number" name="expected_facings" value="20" class="w-full bg-gray-800 border border-gray-600 rounded p-2 text-white text-sm">
                        </div>
                        <div>
                            <label class="block text-xs text-gray-300 mb-1">Facings Terdeteksi</label>
                            <input type="number" name="detected_facings" value="18" class="w-full bg-gray-800 border border-gray-600 rounded p-2 text-white text-sm">
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs text-gray-300 mb-1">Produk Kosong (Out of Stock)</label>
                        <input type="number" name="out_of_stock_detected" value="1" class="w-full bg-gray-800 border border-gray-600 rounded p-2 text-white text-sm">
                    </div>
                    <button type="submit" class="w-full py-2 px-4 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded transition">
                        Audit Kepatuhan Planogram
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

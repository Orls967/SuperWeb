@extends('layouts.app')

@section('content')
<div class="py-12">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col space-y-6">
        <div class="p-6 bg-gray-800 shadow rounded-lg border border-gray-700">
            <h1 class="text-2xl font-bold text-white">Media & Creative Ecosystem Dashboard</h1>
            <p class="mt-2 text-sm text-gray-400">Pusat kendali studio produksi, hak cipta IP, lisensi multi-wilayah, dan periklanan programmatic.</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="p-5 bg-gray-800/80 rounded-lg border border-gray-700 flex flex-col justify-between">
                <div>
                    <h3 class="text-lg font-semibold text-white">Studio Sound Stage</h3>
                    <p class="text-sm text-gray-400 mt-1">Pemesanan fasilitas studio per 30 menit tanpa bentrok.</p>
                </div>
                <div class="mt-4">
                    <a href="/media/studios" class="inline-flex items-center px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded">Kelola Studio</a>
                </div>
            </div>

            <div class="p-5 bg-gray-800/80 rounded-lg border border-gray-700 flex flex-col justify-between">
                <div>
                    <h3 class="text-lg font-semibold text-white">Lisensi IP & Royalti</h3>
                    <p class="text-sm text-gray-400 mt-1">Interval hak cipta non-overlap dan distribusi waterfall royalti.</p>
                </div>
                <div class="mt-4">
                    <a href="/media/licensing" class="inline-flex items-center px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white text-sm font-medium rounded">Katalog Lisensi</a>
                </div>
            </div>

            <div class="p-5 bg-gray-800/80 rounded-lg border border-gray-700 flex flex-col justify-between">
                <div>
                    <h3 class="text-lg font-semibold text-white">Periklanan Programmatic</h3>
                    <p class="text-sm text-gray-400 mt-1">Ingesti impresi anti-fraud dan pembagian komisi multi-pihak.</p>
                </div>
                <div class="mt-4">
                    <a href="/media/production/ads" class="inline-flex items-center px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-medium rounded">Kampanye Iklan</a>
                </div>
            </div>
        </div>

        <div class="overflow-x-auto bg-gray-800 rounded-lg border border-gray-700 p-4">
            <h4 class="text-sm font-medium text-gray-300 mb-2">Ringkasan Sistem Media Terpadu</h4>
            <p class="text-xs text-gray-400">Audit dua sumber aktif, perlindungan anti-IDOR, dan pencatatan double-entry ledger.</p>
        </div>
    </div>
</div>
@endsection


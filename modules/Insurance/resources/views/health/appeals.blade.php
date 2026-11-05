@extends('layouts.app')

@section('content')
<div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="mb-6">
        <a href="{{ route('insurance.health.claims.index') }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-900">&larr; Kembali ke Daftar Klaim</a>
        <h1 class="text-2xl font-bold text-gray-900 mt-2">Banding Klaim Kesehatan (Claim Appeal Workflow)</h1>
        <p class="mt-1 text-sm text-gray-600">Penelaahan kembali berkas klaim yang ditolak atau tidak ter-cover penuh oleh Medical Director.</p>
    </div>

    <div class="bg-white shadow overflow-hidden sm:rounded-lg">
        <div class="px-4 py-5 sm:px-6">
            <h3 class="text-lg leading-6 font-medium text-gray-900">Daftar Pengajuan Banding Aktif</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">No. Klaim</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Alasan Penolakan Awal</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Alasan Banding Pemohon</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <tr>
                        <td colspan="4" class="px-6 py-8 text-center text-sm text-gray-500">Seluruh pengajuan banding telah diproses.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection


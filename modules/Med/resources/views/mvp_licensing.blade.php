@extends('layouts.app')

@section('content')
<div class="py-12">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col space-y-6">
        <div class="p-6 bg-gray-800 shadow rounded-lg border border-gray-700">
            <h1 class="text-2xl font-bold text-white">IP Rights & Licensing Catalog</h1>
            <p class="mt-2 text-sm text-gray-400">Pemberian lisensi interval half-open [start_date, end_date) dan pencegahan overlap eksklusif.</p>
        </div>

        <div class="grid grid-cols-1 gap-6">
            <div class="overflow-x-auto bg-gray-800 rounded-lg border border-gray-700 p-4">
                <table class="w-full text-left text-sm text-gray-300">
                    <thead class="bg-gray-700 text-xs text-gray-400 uppercase">
                        <tr>
                            <th class="p-3">Kode Lisensi</th>
                            <th class="p-3">IP Asset</th>
                            <th class="p-3">Licensee</th>
                            <th class="p-3">Hak</th>
                            <th class="p-3">Wilayah</th>
                            <th class="p-3">Eksklusif</th>
                            <th class="p-3">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($licenses ?? [] as $license)
                            <tr class="border-b border-gray-700">
                                <td class="p-3 font-mono">{{ $license->license_code }}</td>
                                <td class="p-3">{{ $license->ipAsset->title ?? $license->ip_id }}</td>
                                <td class="p-3">{{ $license->licensee_entity_id }}</td>
                                <td class="p-3">{{ $license->rights_type }}</td>
                                <td class="p-3">{{ $license->territory }}</td>
                                <td class="p-3">{{ $license->is_exclusive ? 'Ya' : 'Tidak' }}</td>
                                <td class="p-3">{{ $license->status }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="p-4 text-center text-gray-500">Tidak ada lisensi aktif.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection


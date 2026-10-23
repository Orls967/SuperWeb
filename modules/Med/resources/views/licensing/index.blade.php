@extends('layouts.app')

@section('content')
<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <div class="p-4 sm:p-8 bg-gray-800 shadow sm:rounded-lg">
            <h2 class="text-xl font-semibold text-white">IP Assets & Territorial Content Rights Licensing</h2>
            <p class="mt-1 text-sm text-gray-400">Pencatatan Kekayaan Intelektual (HAKI) & Lisensi Teritori/Kanal</p>

            <div class="mt-6 space-y-3">
                @forelse($ipAssets as $ip)
                    <div class="p-4 bg-gray-700/50 rounded-lg border border-gray-600 flex justify-between items-center">
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="font-mono text-white font-medium">{{ $ip->ip_code }}</span>
                                <span class="text-xs px-2 py-0.5 rounded bg-indigo-900 text-indigo-200">{{ $ip->ip_type }}</span>
                            </div>
                            <div class="text-white text-md font-semibold mt-1">{{ $ip->title }}</div>
                            <div class="text-xs text-gray-400">Pemilik Hak: {{ $ip->owner_entity_id }}</div>
                        </div>
                        <a href="{{ route('med.licensing.show', $ip->id) }}" class="py-1.5 px-3 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-medium rounded transition">
                            Kelola Lisensi &rarr;
                        </a>
                    </div>
                @empty
                    <div class="text-gray-400 text-sm">Belum ada aset IP terdaftar.</div>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection

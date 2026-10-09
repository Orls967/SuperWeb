<x-app-layout>
    <div class="py-6 max-w-7xl mx-auto px-4">
        <h1 class="text-2xl font-bold mb-4">Direktori Mitra & Kemitraan</h1>
        <div class="bg-white rounded shadow p-4">
            <p class="text-gray-600 mb-4">Total mitra terdaftar: {{ $partners->count() }}</p>
            <table class="w-full text-left">
                <thead>
                    <tr class="border-b">
                        <th class="py-2">Kode</th>
                        <th>Nama</th>
                        <th>Jenis</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($partners as $partner)
                        <tr class="border-b hover:bg-gray-50">
                            <td class="py-2 font-mono font-semibold">{{ $partner->code }}</td>
                            <td>{{ $partner->name }}</td>
                            <td><span class="px-2 py-1 text-xs bg-blue-100 text-blue-800 rounded">{{ $partner->kind }}</span></td>
                            <td><span class="px-2 py-1 text-xs bg-green-100 text-green-800 rounded">{{ $partner->status }}</span></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>

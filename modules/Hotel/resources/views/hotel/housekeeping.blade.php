@extends('layouts.app')

@section('title', 'Papan Operasional Housekeeping')

@section('content')
<div class="container mx-auto px-4 py-8">
    <h1 class="text-2xl font-bold mb-6">Papan Housekeeping</h1>

    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8">
        @foreach($rooms as $room)
        <div class="bg-white shadow rounded p-4 border-l-4 @if($room->status === 'dirty') border-red-500 @elseif($room->status === 'cleaning') border-yellow-500 @elseif($room->status === 'inspected') border-blue-500 @elseif($room->status === 'available') border-green-500 @else border-gray-500 @endif">
            <div class="flex justify-between items-center mb-2">
                <span class="font-bold text-lg">{{ $room->room_number }}</span>
                <span class="px-2 py-0.5 text-xs rounded uppercase font-semibold">{{ $room->status }}</span>
            </div>
            <p class="text-xs text-gray-500">{{ $room->roomType->name ?? $room->room_type }}</p>
        </div>
        @endforeach
    </div>
</div>
@endsection

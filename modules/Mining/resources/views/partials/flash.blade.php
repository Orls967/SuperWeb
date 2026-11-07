@foreach (['success' => 'emerald', 'error' => 'rose'] as $key => $color)
    @if (session($key))
        <div class="mb-4 p-4 bg-{{ $color }}-900/50 border border-{{ $color }}-500 rounded text-{{ $color }}-200 text-sm">{{ session($key) }}</div>
    @endif
@endforeach

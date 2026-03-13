<div class="px-4 py-3">
    @if ($url = $getMediaUrl())
        <img src="{{ $url }}" class="w-10 h-10 rounded object-cover" />
    @else
        <span class="text-gray-400">&mdash;</span>
    @endif
</div>

@php
    $logs = $getState() ?? [];
@endphp

<div class="space-y-2 font-mono text-sm bg-gray-900 dark:bg-gray-950 p-4 rounded-lg overflow-auto max-h-96">
    @forelse ($logs as $log)
        @php
            $levelColor = match ($log['level'] ?? 'info') {
                'error' => 'text-red-400',
                'warning' => 'text-yellow-400',
                'success' => 'text-green-400',
                default => 'text-gray-300',
            };
            $timestamp = \Carbon\Carbon::parse($log['timestamp'] ?? now())->format('H:i:s');
        @endphp
        <div class="flex gap-2">
            <span class="text-gray-500 shrink-0">[{{ $timestamp }}]</span>
            <span class="{{ $levelColor }}">{{ $log['message'] ?? '' }}</span>
        </div>
    @empty
        <div class="text-gray-500 italic">Brak logów</div>
    @endforelse
</div>

@if (!empty($logs))
    <div class="mt-2 text-xs text-gray-500">
        {{ count($logs) }} wpisów
    </div>
@endif

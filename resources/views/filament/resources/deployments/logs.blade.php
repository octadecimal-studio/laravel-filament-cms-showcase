<div class="space-y-2">
    @forelse($logs as $log)
        <div class="p-3 rounded-lg border @if($log['level'] === 'error') bg-red-50 border-red-200 @elseif($log['level'] === 'warning') bg-yellow-50 border-yellow-200 @else bg-gray-50 border-gray-200 @endif">
            <div class="flex items-start justify-between">
                <div class="flex-1">
                    <p class="text-sm font-medium text-gray-900">{{ $log['message'] ?? '' }}</p>
                    <p class="text-xs text-gray-500 mt-1">{{ $log['timestamp'] ?? '' }}</p>
                </div>
                <span class="px-2 py-1 text-xs font-medium rounded @if($log['level'] === 'error') bg-red-100 text-red-800 @elseif($log['level'] === 'warning') bg-yellow-100 text-yellow-800 @else bg-gray-100 text-gray-800 @endif">
                    {{ strtoupper($log['level'] ?? 'info') }}
                </span>
            </div>
        </div>
    @empty
        <p class="text-sm text-gray-500">Brak logów</p>
    @endforelse
</div>

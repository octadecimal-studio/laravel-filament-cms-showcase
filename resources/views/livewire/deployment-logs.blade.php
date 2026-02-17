<div wire:poll.2s="refresh" class="space-y-2">
    @if($deployment)
        <div class="mb-4">
            <div class="flex items-center justify-between">
                <h3 class="text-lg font-semibold">Logi deploymentu #{{ $deployment->id }}</h3>
                <span class="px-3 py-1 text-sm font-medium rounded-full @if($status === 'completed') bg-green-100 text-green-800 @elseif($status === 'failed') bg-red-100 text-red-800 @elseif($status === 'in_progress') bg-yellow-100 text-yellow-800 @else bg-gray-100 text-gray-800 @endif">
                    @if($status === 'completed')
                        ✅ Zakończony
                    @elseif($status === 'failed')
                        ❌ Nieudany
                    @elseif($status === 'in_progress')
                        ⏳ W trakcie
                    @else
                        ⏸️ {{ ucfirst($status) }}
                    @endif
                </span>
            </div>
            @if($deployment->version)
                <p class="text-sm text-gray-500 mt-1">Wersja: {{ $deployment->version }}</p>
            @endif
        </div>

        <div class="space-y-2 max-h-96 overflow-y-auto">
            @forelse($logs as $log)
                <div class="p-3 rounded-lg border @if(($log['level'] ?? 'info') === 'error') bg-red-50 border-red-200 @elseif(($log['level'] ?? 'info') === 'warning') bg-yellow-50 border-yellow-200 @else bg-gray-50 border-gray-200 @endif">
                    <div class="flex items-start justify-between">
                        <div class="flex-1">
                            <p class="text-sm font-medium text-gray-900">{{ $log['message'] ?? '' }}</p>
                            @if(isset($log['timestamp']))
                                <p class="text-xs text-gray-500 mt-1">{{ \Carbon\Carbon::parse($log['timestamp'])->format('d.m.Y H:i:s') }}</p>
                            @endif
                        </div>
                        <span class="px-2 py-1 text-xs font-medium rounded @if(($log['level'] ?? 'info') === 'error') bg-red-100 text-red-800 @elseif(($log['level'] ?? 'info') === 'warning') bg-yellow-100 text-yellow-800 @else bg-gray-100 text-gray-800 @endif">
                            {{ strtoupper($log['level'] ?? 'info') }}
                        </span>
                    </div>
                </div>
            @empty
                <p class="text-sm text-gray-500 text-center py-4">Brak logów</p>
            @endforelse
        </div>

        @if($status === 'in_progress')
            <div class="mt-4 text-center">
                <div class="inline-flex items-center text-sm text-gray-600">
                    <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-gray-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    Odświeżanie co 2 sekundy...
                </div>
            </div>
        @endif
    @else
        <p class="text-sm text-gray-500 text-center py-4">Deployment nie znaleziony</p>
    @endif
</div>

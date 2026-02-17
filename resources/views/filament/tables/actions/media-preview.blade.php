{{-- Modal podglądu mediów --}}
<div class="space-y-4 p-4">
    @if($record->isImage())
        <div class="relative rounded-lg overflow-hidden bg-gray-100 dark:bg-gray-800 flex items-center justify-center" style="min-height: 300px;">
            <img 
                src="{{ $record->getUrl() }}" 
                alt="{{ $record->alt_text ?? $record->file_name }}"
                class="max-w-full max-h-[60vh] object-contain"
            />
        </div>
        
        <div class="flex flex-wrap gap-2 justify-center">
            <a 
                href="{{ $record->getUrl() }}" 
                target="_blank"
                class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-primary-600 rounded-lg hover:bg-primary-700 transition-colors"
            >
                <x-heroicon-o-arrow-top-right-on-square class="w-4 h-4" />
                Otwórz oryginał
            </a>
            
            <a 
                href="{{ $record->getUrl() }}" 
                download="{{ $record->file_name }}"
                class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
            >
                <x-heroicon-o-arrow-down-tray class="w-4 h-4" />
                Pobierz
            </a>
            
            <button 
                type="button"
                x-data
                @click="navigator.clipboard.writeText('{{ $record->getUrl() }}'); $dispatch('notify', { message: 'URL skopiowany!' });"
                class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
            >
                <x-heroicon-o-clipboard-document class="w-4 h-4" />
                Kopiuj URL
            </button>
        </div>
        
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3 text-sm">
            <div class="p-3 bg-gray-50 dark:bg-gray-800/50 rounded-lg text-center">
                <div class="text-gray-500 dark:text-gray-400 text-xs uppercase tracking-wide mb-1">Wymiary</div>
                <div class="font-semibold text-gray-900 dark:text-white">
                    {{ $record->width ?? '?' }} × {{ $record->height ?? '?' }}
                </div>
            </div>
            <div class="p-3 bg-gray-50 dark:bg-gray-800/50 rounded-lg text-center">
                <div class="text-gray-500 dark:text-gray-400 text-xs uppercase tracking-wide mb-1">Rozmiar</div>
                <div class="font-semibold text-gray-900 dark:text-white">
                    {{ $record->size ? number_format($record->size / 1024, 1) . ' KB' : '?' }}
                </div>
            </div>
            <div class="p-3 bg-gray-50 dark:bg-gray-800/50 rounded-lg text-center">
                <div class="text-gray-500 dark:text-gray-400 text-xs uppercase tracking-wide mb-1">Format</div>
                <div class="font-semibold text-gray-900 dark:text-white">
                    {{ strtoupper(pathinfo($record->file_name, PATHINFO_EXTENSION)) }}
                </div>
            </div>
            <div class="p-3 bg-gray-50 dark:bg-gray-800/50 rounded-lg text-center">
                <div class="text-gray-500 dark:text-gray-400 text-xs uppercase tracking-wide mb-1">Dodano</div>
                <div class="font-semibold text-gray-900 dark:text-white">
                    {{ $record->created_at?->format('d.m.Y') }}
                </div>
            </div>
        </div>
        
        @if($record->alt_text || $record->caption)
        <div class="space-y-2 pt-2 border-t border-gray-200 dark:border-gray-700">
            @if($record->alt_text)
            <div>
                <span class="text-gray-500 dark:text-gray-400 text-xs uppercase tracking-wide">Alt text:</span>
                <span class="text-gray-700 dark:text-gray-300 text-sm ml-2">{{ $record->alt_text }}</span>
            </div>
            @endif
            @if($record->caption)
            <div>
                <span class="text-gray-500 dark:text-gray-400 text-xs uppercase tracking-wide">Podpis:</span>
                <span class="text-gray-700 dark:text-gray-300 text-sm ml-2">{{ $record->caption }}</span>
            </div>
            @endif
        </div>
        @endif
    @endif
</div>

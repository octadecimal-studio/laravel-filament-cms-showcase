{{-- Podgląd mediów z możliwością pobrania --}}
<div class="space-y-4">
    @if($getRecord() && $getRecord()->isImage())
        <div class="relative rounded-lg overflow-hidden bg-gray-100 dark:bg-gray-800 border border-gray-200 dark:border-gray-700">
            <img 
                src="{{ $getRecord()->getUrl() }}" 
                alt="{{ $getRecord()->alt_text ?? $getRecord()->file_name }}"
                class="max-w-full max-h-96 mx-auto object-contain"
            />
        </div>
        
        <div class="flex flex-wrap gap-2">
            <a 
                href="{{ $getRecord()->getUrl() }}" 
                target="_blank"
                class="inline-flex items-center gap-2 px-3 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
            >
                <x-heroicon-o-arrow-top-right-on-square class="w-4 h-4" />
                Otwórz w nowej karcie
            </a>
            
            <a 
                href="{{ $getRecord()->getUrl() }}" 
                download="{{ $getRecord()->file_name }}"
                class="inline-flex items-center gap-2 px-3 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
            >
                <x-heroicon-o-arrow-down-tray class="w-4 h-4" />
                Pobierz
            </a>
            
            <button 
                type="button"
                onclick="navigator.clipboard.writeText('{{ $getRecord()->getUrl() }}'); alert('URL skopiowany!');"
                class="inline-flex items-center gap-2 px-3 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
            >
                <x-heroicon-o-clipboard-document class="w-4 h-4" />
                Kopiuj URL
            </button>
        </div>
        
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
            <div class="p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">
                <div class="text-gray-500 dark:text-gray-400 text-xs uppercase tracking-wide mb-1">Wymiary</div>
                <div class="font-medium text-gray-900 dark:text-white">
                    {{ $getRecord()->width ?? '?' }} × {{ $getRecord()->height ?? '?' }} px
                </div>
            </div>
            <div class="p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">
                <div class="text-gray-500 dark:text-gray-400 text-xs uppercase tracking-wide mb-1">Rozmiar</div>
                <div class="font-medium text-gray-900 dark:text-white">
                    {{ $getRecord()->size ? number_format($getRecord()->size / 1024, 1) . ' KB' : '?' }}
                </div>
            </div>
            <div class="p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">
                <div class="text-gray-500 dark:text-gray-400 text-xs uppercase tracking-wide mb-1">Format</div>
                <div class="font-medium text-gray-900 dark:text-white">
                    {{ strtoupper(pathinfo($getRecord()->file_name, PATHINFO_EXTENSION)) }}
                </div>
            </div>
            <div class="p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">
                <div class="text-gray-500 dark:text-gray-400 text-xs uppercase tracking-wide mb-1">Data</div>
                <div class="font-medium text-gray-900 dark:text-white">
                    {{ $getRecord()->created_at?->format('d.m.Y') }}
                </div>
            </div>
        </div>
    @else
        <div class="p-6 text-center text-gray-500 dark:text-gray-400 bg-gray-50 dark:bg-gray-800 rounded-lg">
            <x-heroicon-o-document class="w-12 h-12 mx-auto mb-2 opacity-50" />
            <p>Podgląd niedostępny dla tego typu pliku</p>
        </div>
    @endif
</div>

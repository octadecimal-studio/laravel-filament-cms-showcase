@php
    use Illuminate\Support\Str;
    $searchResults = session('plugin_search_results', []);
@endphp

<x-filament-panels::page>
    @if (!empty($searchResults))
        <div class="mb-6">
            <x-filament::section>
                <x-slot name="heading">
                    Wyniki wyszukiwania ({{ count($searchResults) }})
                </x-slot>
                
                <x-slot name="description">
                    Kliknij "Zainstaluj" aby dodać plugin do systemu
                </x-slot>
                
                <div class="space-y-4">
                    @foreach ($searchResults as $result)
                        <div class="fi-section-content-ctn rounded-lg border border-gray-200 dark:border-gray-700 p-4 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">
                            <div class="flex items-start justify-between gap-4">
                                <div class="flex-1 min-w-0">
                                    <h3 class="fi-section-header-heading text-lg font-semibold text-gray-950 dark:text-white">
                                        {{ $result['name'] ?? 'N/A' }}
                                    </h3>
                                    <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                                        {{ Str::limit($result['description'] ?? 'Brak opisu', 150) }}
                                    </p>
                                    <div class="flex items-center gap-4 mt-3 text-xs text-gray-500 dark:text-gray-400">
                                        @if (isset($result['downloads']))
                                            <span class="flex items-center gap-1">
                                                <x-heroicon-o-arrow-down-tray class="w-4 h-4" />
                                                {{ number_format($result['downloads']) }} pobrań
                                            </span>
                                        @endif
                                        @if (isset($result['favers']))
                                            <span class="flex items-center gap-1">
                                                <x-heroicon-o-star class="w-4 h-4" />
                                                {{ $result['favers'] }} gwiazdek
                                            </span>
                                        @endif
                                    </div>
                                </div>
                                <div class="flex gap-2 shrink-0">
                                    <a 
                                        href="{{ \App\Filament\Resources\Modules\Plugins\PluginResource::getUrl('create', ['package' => $result['name'] ?? '']) }}"
                                        class="fi-btn fi-btn-color-primary fi-btn-size-sm"
                                    >
                                        <span class="fi-btn-label">Zainstaluj</span>
                                    </a>
                                    @if (isset($result['url']))
                                        <a 
                                            href="{{ $result['url'] }}" 
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            class="fi-btn fi-btn-color-gray fi-btn-size-sm"
                                        >
                                            <span class="fi-btn-label">Szczegóły</span>
                                        </a>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </x-filament::section>
        </div>
    @endif

    {{ $this->table }}
</x-filament-panels::page>

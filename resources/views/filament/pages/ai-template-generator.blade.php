<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Formularz --}}
        <x-filament::section>
            <x-slot name="heading">
                Generator szablonów AI
            </x-slot>

            <x-slot name="description">
                Wygeneruj szablon Next.js używając AI. Opisz, jaki szablon chcesz utworzyć, a AI wygeneruje dla Ciebie kompletny kod.
            </x-slot>

            {{ $this->form }}
        </x-filament::section>

        {{-- Progress indicator --}}
        @if($this->generationStatus === 'generating')
            <x-filament::section>
                <div class="flex items-center gap-4">
                    <x-filament::loading-indicator class="w-6 h-6" />
                    <div>
                        <p class="font-semibold">Generowanie szablonu...</p>
                        <p class="text-sm text-gray-500">To może chwilę potrwać (30-60 sekund)</p>
                    </div>
                </div>
            </x-filament::section>
        @endif

        {{-- Błąd --}}
        @if($this->generationStatus === 'failed' && $this->generationError)
            <x-filament::section>
                <div class="rounded-lg bg-danger-50 dark:bg-danger-900/20 border border-danger-200 dark:border-danger-800 p-4">
                    <div class="flex items-start gap-3">
                        <div class="flex-shrink-0">
                            <svg class="w-5 h-5 text-danger-600 dark:text-danger-400" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                            </svg>
                        </div>
                        <div class="flex-1">
                            <h3 class="text-sm font-semibold text-danger-800 dark:text-danger-200 mb-1">Błąd generowania</h3>
                            <p class="text-sm text-danger-700 dark:text-danger-300">{{ $this->generationError }}</p>
                        </div>
                    </div>
                </div>
            </x-filament::section>
        @endif

        {{-- Preview wygenerowanego szablonu --}}
        @if($this->generationStatus === 'completed' && $this->generatedTemplate)
            <x-filament::section>
                <x-slot name="heading">
                    Wygenerowany szablon
                </x-slot>

                <div class="space-y-4">
                    {{-- Metadane --}}
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <p class="text-sm font-medium text-gray-500">Model</p>
                            <p class="text-lg">{{ $this->generatedTemplate->model }}</p>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-500">Status</p>
                            <p class="text-lg">
                                <x-filament::badge color="success">
                                    {{ $this->generatedTemplate->status }}
                                </x-filament::badge>
                            </p>
                        </div>
                        @if($this->generatedTemplate->metadata)
                            <div>
                                <p class="text-sm font-medium text-gray-500">Tokeny</p>
                                <p class="text-lg">
                                    @php
                                        $tokens = $this->generatedTemplate->metadata['tokens_total'] ?? null;
                                        echo is_array($tokens) ? json_encode($tokens, JSON_PRETTY_PRINT) : ($tokens ?? 'N/A');
                                    @endphp
                                </p>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-500">Czas generowania</p>
                                <p class="text-lg">
                                    @php
                                        $time = $this->generatedTemplate->metadata['time'] ?? null;
                                        echo is_array($time) ? json_encode($time, JSON_PRETTY_PRINT) : ($time ?? 'N/A');
                                    @endphp
                                </p>
                            </div>
                        @endif
                    </div>

                    {{-- Wygenerowany kod --}}
                    @if($this->generatedCode)
                        <div>
                            <p class="text-sm font-medium text-gray-500 mb-2">Komponenty</p>
                            <div class="space-y-4">
                                @foreach($this->generatedCode['components'] ?? [] as $component)
                                    <div class="border rounded-lg p-4 bg-gray-50 dark:bg-gray-800">
                                        <div class="flex items-center justify-between mb-2">
                                            <h4 class="font-semibold">{{ $component['name'] ?? 'Unnamed' }}</h4>
                                            <x-filament::badge>{{ $component['type'] ?? 'tsx' }}</x-filament::badge>
                                        </div>
                                        <pre class="text-xs overflow-x-auto bg-white dark:bg-gray-900 p-4 rounded border"><code>@php
                                            $code = $component['code'] ?? '';
                                            echo is_array($code) ? json_encode($code, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : (is_string($code) ? htmlspecialchars($code, ENT_QUOTES, 'UTF-8') : '');
                                        @endphp</code></pre>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    {{-- Akcje --}}
                    <div class="flex gap-4 pt-4 border-t">
                        <x-filament::button
                            wire:click="saveAsContentTemplate"
                            color="success"
                        >
                            <x-slot name="icon">
                                <x-heroicon-o-check class="w-5 h-5" />
                            </x-slot>
                            Zapisz jako ContentTemplate
                        </x-filament::button>

                        <x-filament::button
                            type="button"
                            color="gray"
                            wire:click="resetForm"
                        >
                            Wygeneruj nowy
                        </x-filament::button>
                    </div>
                </div>
            </x-filament::section>
        @endif
    </div>
</x-filament-panels::page>

<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Informacja o tenancie --}}
        <x-filament::section>
            <x-slot name="heading">
                Informacje o kliencie
            </x-slot>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <span class="text-sm font-medium text-gray-500 dark:text-gray-400">Nazwa</span>
                    <p class="text-base font-semibold">{{ $record->name }}</p>
                </div>
                <div>
                    <span class="text-sm font-medium text-gray-500 dark:text-gray-400">Slug</span>
                    <p class="text-base font-mono">{{ $record->slug }}</p>
                </div>
                <div>
                    <span class="text-sm font-medium text-gray-500 dark:text-gray-400">Plan</span>
                    <x-filament::badge :color="match($record->plan) {
                        'enterprise' => 'success',
                        'pro' => 'info',
                        default => 'gray',
                    }">
                        {{ \App\Modules\Core\Models\Tenant::PLANS[$record->plan] ?? $record->plan }}
                    </x-filament::badge>
                </div>
            </div>
        </x-filament::section>

        {{-- Macierz dostępów --}}
        @foreach ($this->getGroupedFeatures() as $groupKey => $group)
            <x-filament::section :collapsible="true">
                <x-slot name="heading">
                    {{ $group['label'] }}
                </x-slot>

                <x-slot name="headerEnd">
                    <div class="flex gap-2">
                        <x-filament::button
                            size="xs"
                            color="success"
                            wire:click="selectAllForGroup('{{ $groupKey }}')"
                        >
                            Zaznacz wszystkie
                        </x-filament::button>
                        <x-filament::button
                            size="xs"
                            color="gray"
                            wire:click="deselectAllForGroup('{{ $groupKey }}')"
                        >
                            Odznacz wszystkie
                        </x-filament::button>
                    </div>
                </x-slot>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 dark:border-gray-700">
                                <th class="text-left py-3 px-4 font-medium text-gray-600 dark:text-gray-400">
                                    Funkcjonalność
                                </th>
                                <th class="text-center py-3 px-4 font-medium text-gray-600 dark:text-gray-400 w-24">
                                    Podgląd
                                </th>
                                <th class="text-center py-3 px-4 font-medium text-gray-600 dark:text-gray-400 w-24">
                                    Tworzenie
                                </th>
                                <th class="text-center py-3 px-4 font-medium text-gray-600 dark:text-gray-400 w-24">
                                    Edycja
                                </th>
                                <th class="text-center py-3 px-4 font-medium text-gray-600 dark:text-gray-400 w-24">
                                    Usuwanie
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($group['features'] as $featureKey => $feature)
                                <tr class="border-b border-gray-100 dark:border-gray-800 hover:bg-gray-50 dark:hover:bg-gray-900/50">
                                    <td class="py-3 px-4 font-medium">
                                        {{ $feature['label'] }}
                                    </td>
                                    <td class="text-center py-3 px-4">
                                        <x-filament::input.checkbox
                                            wire:model.live="access.{{ $featureKey }}.can_view"
                                        />
                                    </td>
                                    <td class="text-center py-3 px-4">
                                        <x-filament::input.checkbox
                                            wire:model.live="access.{{ $featureKey }}.can_create"
                                        />
                                    </td>
                                    <td class="text-center py-3 px-4">
                                        <x-filament::input.checkbox
                                            wire:model.live="access.{{ $featureKey }}.can_edit"
                                        />
                                    </td>
                                    <td class="text-center py-3 px-4">
                                        <x-filament::input.checkbox
                                            wire:model.live="access.{{ $featureKey }}.can_delete"
                                        />
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-filament::section>
        @endforeach

        {{-- Przycisk zapisz --}}
        <div class="flex justify-end">
            <x-filament::button
                wire:click="save"
                icon="heroicon-o-check"
                size="lg"
            >
                Zapisz dostępy
            </x-filament::button>
        </div>
    </div>
</x-filament-panels::page>

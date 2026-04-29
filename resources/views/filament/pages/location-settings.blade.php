<x-filament-panels::page>
    <form wire:submit="save">
        {{ $this->form }}

        <div class="fi-form-actions sticky bottom-0 -mx-6 px-6 py-4 mt-6 flex justify-end gap-3 bg-white dark:bg-gray-950 border-t border-gray-200 dark:border-gray-800 z-20 lg:-mx-8 lg:px-8">
            <x-filament::button type="submit" color="primary" size="md">
                Zapisz zmiany
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>

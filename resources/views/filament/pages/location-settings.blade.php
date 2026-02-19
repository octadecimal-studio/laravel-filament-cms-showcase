<x-filament-panels::page>
    <form wire:submit="save">
        {{ $this->form }}

        <div class="fi-form-actions mt-6 flex justify-end gap-3">
            <x-filament::button type="submit" color="primary" size="md">
                Zapisz zmiany
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>

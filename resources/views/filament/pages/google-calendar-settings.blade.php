<x-filament-panels::page>
    @if(session('gcal_success'))
        <div class="p-4 mb-4 text-green-800 bg-green-50 rounded-lg border border-green-200 dark:bg-green-900/20 dark:text-green-400 dark:border-green-800">
            {{ session('gcal_success') }}
        </div>
    @endif

    @if(session('gcal_error'))
        <div class="p-4 mb-4 text-red-800 bg-red-50 rounded-lg border border-red-200 dark:bg-red-900/20 dark:text-red-400 dark:border-red-800">
            {{ session('gcal_error') }}
        </div>
    @endif

    <form wire:submit="saveSettings">
        {{ $this->form }}
    </form>
</x-filament-panels::page>

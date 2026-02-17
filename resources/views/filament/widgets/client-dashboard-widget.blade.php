<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            Witaj w panelu
        </x-slot>

        <x-slot name="description">
            Szybki dostęp do Twoich sekcji
        </x-slot>

        @php
            $links = $this->getQuickLinks();
        @endphp

        @if ($links)
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4">
                @foreach ($links as $link)
                    <a
                        href="{{ $link['url'] }}"
                        class="flex items-center gap-3 rounded-xl border border-gray-200 bg-white p-4 shadow-sm transition hover:border-primary-500 hover:shadow dark:border-gray-700 dark:bg-gray-800 dark:hover:border-primary-500"
                    >
                        <x-filament::icon
                            :icon="$link['icon']"
                            class="h-8 w-8 text-primary-500"
                        />
                        <span class="font-medium text-gray-900 dark:text-white">
                            {{ $link['label'] }}
                        </span>
                    </a>
                @endforeach
            </div>
        @else
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Nie masz przypisanych sekcji. Skontaktuj się z administratorem.
            </p>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>

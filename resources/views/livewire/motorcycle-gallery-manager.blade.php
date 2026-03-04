<div>
    {{-- Existing gallery images --}}
    @if($images->isNotEmpty())
        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3 mb-4">
            @foreach($images as $media)
                <div
                    x-data="{ hovered: false }"
                    x-on:mouseenter="hovered = true"
                    x-on:mouseleave="hovered = false"
                    class="relative aspect-square rounded-lg overflow-hidden shadow-sm border border-gray-200 dark:border-gray-700"
                >
                    <img
                        src="{{ asset('storage/' . $media->file_path) }}"
                        alt="{{ $media->alt_text ?? $media->file_name }}"
                        class="w-full h-full object-cover"
                    />

                    {{-- Delete button on hover --}}
                    <button
                        x-show="hovered"
                        x-transition:enter="transition ease-out duration-150"
                        x-transition:enter-start="opacity-0 scale-90"
                        x-transition:enter-end="opacity-100 scale-100"
                        x-on:click="$wire.mountAction('deleteImage', { id: '{{ $media->id }}' })"
                        type="button"
                        class="absolute top-1 right-1 w-7 h-7 flex items-center justify-center rounded-full bg-red-500 hover:bg-red-600 text-white text-sm font-bold shadow-lg cursor-pointer"
                        title="Usuń zdjęcie"
                    >
                        &times;
                    </button>
                </div>
            @endforeach
        </div>
    @else
        <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">Brak zdjęć w galerii</p>
    @endif

    {{-- Drop zone for new images --}}
    <div
        x-data="{
            dragging: false,
            handleDrop(e) {
                this.dragging = false;
                const files = e.dataTransfer.files;
                if (files.length === 0) return;
                const input = this.$refs.fileInput;
                const dt = new DataTransfer();
                for (const f of files) {
                    if (f.type.startsWith('image/')) dt.items.add(f);
                }
                if (dt.files.length > 0) {
                    input.files = dt.files;
                    input.dispatchEvent(new Event('change', { bubbles: true }));
                }
            }
        }"
        x-on:dragover.prevent="dragging = true"
        x-on:dragleave.prevent="dragging = false"
        x-on:drop.prevent="handleDrop($event)"
        class="relative border-2 border-dashed rounded-lg p-6 text-center transition-colors"
        x-bind:class="dragging ? 'border-primary-500 bg-primary-50 dark:bg-primary-900/20' : 'border-gray-300 dark:border-gray-600 hover:border-gray-400'"
    >
        <div class="flex flex-col items-center gap-2">
            <svg class="w-8 h-8 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5" />
            </svg>
            <p class="text-sm text-gray-600 dark:text-gray-400">
                Przeciągnij zdjęcia tutaj lub
                <label class="text-primary-600 hover:text-primary-500 cursor-pointer font-medium">
                    wybierz pliki
                    <input
                        x-ref="fileInput"
                        type="file"
                        wire:model="newFiles"
                        multiple
                        accept="image/jpeg,image/png,image/webp"
                        class="sr-only"
                    />
                </label>
            </p>
            <p class="text-xs text-gray-400">JPG, PNG, WebP — max 5 MB</p>
        </div>

        {{-- Upload progress --}}
        <div wire:loading wire:target="newFiles" class="mt-3">
            <div class="flex items-center justify-center gap-2 text-sm text-primary-600">
                <svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                </svg>
                Wgrywanie...
            </div>
        </div>
    </div>

    {{-- Preview new files and upload button --}}
    @if(!empty($newFiles))
        <div class="mt-3 flex items-center gap-3">
            <p class="text-sm text-gray-600 dark:text-gray-400">
                {{ count($newFiles) }} {{ count($newFiles) === 1 ? 'plik gotowy' : 'pliki gotowe' }} do wgrania
            </p>
            <button
                wire:click="uploadFiles"
                type="button"
                class="inline-flex items-center gap-1 px-3 py-1.5 bg-primary-600 hover:bg-primary-500 text-white text-sm font-medium rounded-lg transition-colors"
            >
                Wgraj
            </button>
        </div>
    @endif

    {{-- Filament action modals --}}
    <x-filament-actions::modals />
</div>

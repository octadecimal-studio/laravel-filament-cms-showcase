@props(['previewUrl', 'screenshotUrl'])

<div class="space-y-4">
    @if($previewUrl)
        <div>
            <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Podgląd interaktywny</h3>
            <div class="border border-gray-300 dark:border-gray-700 rounded-lg overflow-hidden">
                <iframe 
                    src="{{ $previewUrl }}" 
                    class="w-full"
                    style="height: 600px; border: none;"
                    loading="lazy"
                    title="Preview szablonu">
                </iframe>
            </div>
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">
                <a href="{{ $previewUrl }}" target="_blank" class="text-primary-600 hover:text-primary-700">
                    Otwórz w nowej karcie →
                </a>
            </p>
        </div>
    @endif

    @if($screenshotUrl)
        <div>
            <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Screenshot</h3>
            <div class="border border-gray-300 dark:border-gray-700 rounded-lg overflow-hidden">
                <img 
                    src="{{ $screenshotUrl }}" 
                    alt="Screenshot szablonu"
                    class="w-full h-auto"
                    loading="lazy">
            </div>
        </div>
    @endif

    @if(!$previewUrl && !$screenshotUrl)
        <div class="text-center py-8 text-gray-500 dark:text-gray-400">
            <p>Brak dostępnego podglądu dla tego szablonu.</p>
            <p class="text-sm mt-2">Dodaj screenshot.png do katalogu szablonu lub zbuduj wersję w out/</p>
        </div>
    @endif
</div>

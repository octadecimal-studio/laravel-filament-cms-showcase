<?php

declare(strict_types=1);

namespace App\Filament\Resources\Modules\Content\Models\TwoWheels\MotorcycleResource\Pages;

use App\Filament\Resources\Modules\Content\Models\TwoWheels\MotorcycleResource;
use App\Modules\Content\Models\Media;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Strona edycji motocykla.
 */
class EditMotorcycle extends EditRecord
{
    protected static string $resource = MotorcycleResource::class;

    /**
     * Akcje nagłówka.
     *
     * @return array<Actions\Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    /**
     * Przetwarzanie danych przed zapisem.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Przetwórz nowy główny obraz jeśli został wgrany
        if (!empty($data['new_main_image'])) {
            $media = $this->createMediaFromUpload($data['new_main_image'], 'motorcycles');
            $data['main_image_id'] = $media->id;
        }
        unset($data['new_main_image']);
        unset($data['new_gallery_images']); // Będzie obsłużone w afterSave

        return $data;
    }

    /**
     * Po zapisie rekordu - obsługa galerii.
     */
    protected function afterSave(): void
    {
        $data = $this->form->getState();

        // Przetwórz nowe zdjęcia galerii
        if (!empty($data['new_gallery_images'])) {
            $mediaIds = [];
            foreach ($data['new_gallery_images'] as $filePath) {
                $media = $this->createMediaFromUpload($filePath, 'motorcycles-gallery');
                $mediaIds[] = $media->id;
            }
            // Dodaj nowe zdjęcia do istniejącej galerii
            $this->record->gallery()->syncWithoutDetaching($mediaIds);
        }
    }

    /**
     * Tworzy rekord Media z wgranego pliku.
     */
    private function createMediaFromUpload(string $filePath, string $collection): Media
    {
        $disk = Storage::disk('public');
        $user = auth()->user();
        
        $fileName = basename($filePath);
        $mimeType = $disk->mimeType($filePath) ?? 'image/jpeg';
        $fileSize = $disk->size($filePath);

        // Pobierz tenant_id z motocykla lub użytkownika
        $tenantId = $this->record->tenant_id ?? $user?->tenant_id;

        $media = new Media();
        $media->tenant_id = $tenantId;
        $media->file_name = $fileName;
        $media->file_path = $filePath;
        $media->mime_type = $mimeType;
        $media->size = $fileSize;
        $media->collection = $collection;
        $media->alt_text = Str::beforeLast($fileName, '.');
        $media->disk = 'public';
        $media->save();

        return $media;
    }
}

<?php

declare(strict_types=1);

namespace App\Filament\Resources\Modules\Content\Models\TwoWheels\MotorcycleResource\Pages;

use App\Filament\Resources\Modules\Content\Models\TwoWheels\MotorcycleResource;
use App\Modules\Content\Models\Media;
use App\Modules\Core\Models\Tenant;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Strona tworzenia motocykla.
 */
class CreateMotorcycle extends CreateRecord
{
    protected static string $resource = MotorcycleResource::class;

    /**
     * Przetwarzanie danych przed utworzeniem rekordu.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->ensureCurrentTenant();

        // Przetwórz główny obraz
        if (!empty($data['new_main_image'])) {
            $media = $this->createMediaFromUpload($data['new_main_image'], 'motorcycles');
            $data['main_image_id'] = $media->id;
        }
        unset($data['new_main_image']);
        unset($data['new_gallery_images']); // Będzie obsłużone w afterCreate
        
        return $data;
    }

    /**
     * Po utworzeniu rekordu - obsługa galerii.
     */
    protected function afterCreate(): void
    {
        $data = $this->form->getState();

        // Przetwórz nowe zdjęcia galerii
        if (!empty($data['new_gallery_images'])) {
            $mediaIds = [];
            foreach ($data['new_gallery_images'] as $filePath) {
                $media = $this->createMediaFromUpload($filePath, 'motorcycles-gallery');
                $mediaIds[] = $media->id;
            }
            // Dodaj do galerii
            $this->record->gallery()->syncWithoutDetaching($mediaIds);
        }
    }

    private function ensureCurrentTenant(): void
    {
        if (app()->bound('current_tenant')) {
            return;
        }
        $user = auth()->user();
        $tenantId = $user?->tenant_id
            ?? Tenant::where('slug', 'demo-studio')->where('is_active', true)->value('id')
            ?? Tenant::where('is_active', true)->value('id');
        if ($tenantId) {
            $tenant = Tenant::find($tenantId);
            if ($tenant) {
                app()->instance('current_tenant', $tenant);
            }
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

        // Tenant: rekord (gdy gallery w afterCreate), app, user lub demo-studio
        $tenantId = $this->record?->tenant_id
            ?? (app()->bound('current_tenant') ? app('current_tenant')->id : null)
            ?? $user?->tenant_id
            ?? Tenant::where('slug', 'demo-studio')->where('is_active', true)->value('id')
            ?? Tenant::where('is_active', true)->value('id');

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

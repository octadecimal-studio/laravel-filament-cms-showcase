<?php

declare(strict_types=1);

namespace App\Filament\Resources\Modules\Content\Models\MediaResource\Pages;

use App\Filament\Resources\Modules\Content\Models\MediaResource;
use App\Models\User;
use App\Modules\Content\Services\MediaService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;

class CreateMedia extends CreateRecord
{
    protected static string $resource = MediaResource::class;

    /**
     * Mutate form data before create.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $file = $data['file'] ?? null;

        if (! $file instanceof UploadedFile) {
            throw new \InvalidArgumentException('File is required');
        }

        // Pobierz tenant_id z aktualnego użytkownika
        $user = Auth::user();

        if (! $user instanceof User) {
            throw new \InvalidArgumentException('User must be authenticated');
        }

        $tenantId = $user->tenant_id;

        if (! $tenantId) {
            throw new \InvalidArgumentException('User must belong to a tenant');
        }

        // Upload pliku używając MediaService
        $mediaService = app(MediaService::class);
        $media = $mediaService->upload($file, [
            'tenant_id' => $tenantId,
            'project_id' => $data['project_id'] ?? null,
            'collection' => $data['collection'] ?? 'gallery',
            'alt_text' => $data['alt_text'] ?? null,
            'caption' => $data['caption'] ?? null,
            'tags' => $data['tags'] ?? null,
            'is_public' => $data['is_public'] ?? false,
            'disk' => 'public',
        ]);

        // Zwróć dane media (bez 'file')
        return [
            'id' => $media->id,
            'file_name' => $media->file_name,
            'file_path' => $media->file_path,
            'mime_type' => $media->mime_type,
            'size' => $media->size,
            'width' => $media->width,
            'height' => $media->height,
            'alt_text' => $media->alt_text,
            'caption' => $media->caption,
            'collection' => $media->collection,
            'tags' => $media->tags,
            'is_public' => $media->is_public,
            'is_active' => $media->is_active,
        ];
    }
}

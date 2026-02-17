<?php

declare(strict_types=1);

namespace App\Filament\Resources\Modules\Content\Models\TwoWheels\FeatureResource\Pages;

use App\Filament\Resources\Modules\Content\Models\TwoWheels\FeatureResource;
use App\Modules\Content\Models\Media;
use App\Modules\Core\Models\Tenant;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CreateFeature extends CreateRecord
{
    protected static string $resource = FeatureResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->ensureCurrentTenant();

        if (! empty($data['new_icon'])) {
            $media = $this->createMediaFromUpload($data['new_icon'], 'features-icons');
            $data['icon_id'] = $media->id;
        }
        unset($data['new_icon']);

        return $data;
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

    private function createMediaFromUpload(string $filePath, string $collection): Media
    {
        $disk = Storage::disk('public');
        $user = auth()->user();
        $fileName = basename($filePath);
        $mimeType = $disk->mimeType($filePath) ?? 'image/jpeg';
        $fileSize = $disk->size($filePath);

        $tenantId = app()->bound('current_tenant')
            ? app('current_tenant')->id
            : ($user?->tenant_id
                ?? Tenant::where('slug', 'demo-studio')->where('is_active', true)->value('id')
                ?? Tenant::where('is_active', true)->value('id'));

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

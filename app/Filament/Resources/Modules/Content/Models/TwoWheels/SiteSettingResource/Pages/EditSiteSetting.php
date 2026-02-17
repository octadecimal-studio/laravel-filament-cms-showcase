<?php

declare(strict_types=1);

namespace App\Filament\Resources\Modules\Content\Models\TwoWheels\SiteSettingResource\Pages;

use App\Filament\Resources\Modules\Content\Models\TwoWheels\SiteSettingResource;
use App\Modules\Content\Models\Media;
use App\Modules\Core\Models\Tenant;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class EditSiteSetting extends EditRecord
{
    protected static string $resource = SiteSettingResource::class;

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

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (! empty($data['new_logo'])) {
            $media = $this->createMediaFromUpload($data['new_logo'], 'site-settings-logos');
            $data['logo_id'] = $media->id;
        }
        unset($data['new_logo']);

        return $data;
    }

    private function createMediaFromUpload(string $filePath, string $collection): Media
    {
        $disk = Storage::disk('public');
        $user = auth()->user();
        $fileName = basename($filePath);
        $mimeType = $disk->mimeType($filePath) ?? 'image/jpeg';
        $fileSize = $disk->size($filePath);

        $tenantId = $this->record->tenant_id
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

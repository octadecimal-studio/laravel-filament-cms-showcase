<?php

declare(strict_types=1);

namespace App\Filament\Resources\Modules\Content\Models\TwoWheels\GalleryResource\Pages;

use App\Filament\Resources\Modules\Content\Models\TwoWheels\GalleryResource;
use App\Modules\Content\Models\Media;
use App\Modules\Core\Models\Tenant;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ListGallery extends ListRecords
{
    protected static string $resource = GalleryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('add_photos')
                ->label('Dodaj zdjęcia')
                ->icon('heroicon-o-photo')
                ->color('primary')
                ->visible(fn (): bool => GalleryResource::canCreate())
                ->form([
                    Forms\Components\FileUpload::make('files')
                        ->label('Zdjęcia')
                        ->helperText('Wgraj wiele zdjęć naraz. Kadruj do 1:1. Bez opisów.')
                        ->multiple()
                        ->required()
                        ->image()
                        ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                        ->maxSize(5120)
                        ->disk('public')
                        ->directory('gallery')
                        ->visibility('public')
                        ->imagePreviewHeight('120')
                        ->imageEditor()
                        ->imageEditorAspectRatios(['1:1', null])
                        ->imageEditorEmptyFillColor('#ffffff')
                        ->imageEditorViewportWidth(400)
                        ->imageEditorViewportHeight(400)
                        ->columnSpanFull(),
                ])
                ->action(function (array $data): void {
                    $this->ensureCurrentTenant();
                    $n = 0;
                    foreach ($data['files'] as $path) {
                        $this->createMediaFromUpload($path);
                        $n++;
                    }
                    \Filament\Notifications\Notification::make()
                        ->title($n === 1 ? 'Dodano 1 zdjęcie' : "Dodano {$n} zdjęć")
                        ->success()
                        ->send();
                })
                ->modalSubmitActionLabel('Wgraj'),
        ];
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

    private function createMediaFromUpload(string $filePath): Media
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
        $media->collection = GalleryResource::COLLECTION_GALLERY;
        $media->alt_text = Str::beforeLast($fileName, '.');
        $media->disk = 'public';
        $media->save();

        return $media;
    }
}

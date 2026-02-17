<?php

declare(strict_types=1);

namespace App\Filament\Resources\Modules\Content\Models\MediaResource\Pages;

use App\Filament\Resources\Modules\Content\Models\MediaResource;
use App\Modules\Content\Models\Media;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Storage;

class ViewMedia extends ViewRecord
{
    protected static string $resource = MediaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('edit_image')
                ->label('Edytuj obraz')
                ->icon('heroicon-o-pencil-square')
                ->color('warning')
                ->visible(fn (Media $record): bool => $record->isImage())
                ->form([
                    Forms\Components\Section::make('Aktualny obraz')
                        ->schema([
                            Forms\Components\Placeholder::make('current_image')
                                ->label('')
                                ->content(function (Media $record): \Illuminate\Contracts\Support\Htmlable {
                                    // Użyj metody getUrl() z modelu Media
                                    $url = $record->getUrl();
                                    return new \Illuminate\Support\HtmlString(
                                        '<img src="' . e($url) . '" alt="' . e($record->file_name) . '" 
                                             class="max-h-64 rounded-lg shadow-md object-contain w-full" />'
                                    );
                                })
                                ->columnSpanFull(),
                        ]),

                    Forms\Components\Section::make('Zamień obraz')
                        ->schema([
                            Forms\Components\FileUpload::make('new_file')
                                ->label('Nowy obraz')
                                ->helperText('Wgraj nowy obraz lub użyj edytora do kadrowania')
                                ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                                ->maxSize(10240) // 10MB
                                ->disk('public')
                                ->directory('media')
                                ->visibility('public')
                                ->imagePreviewHeight('250')
                                ->imageEditor()
                                ->imageEditorAspectRatios([
                                    null, // Dowolny
                                    '16:9',
                                    '4:3',
                                    '1:1',
                                    '3:4',
                                    '9:16',
                                ])
                                ->imageEditorEmptyFillColor('#000000')
                                ->openable()
                                ->downloadable()
                                ->required()
                                ->columnSpanFull(),
                        ]),
                ])
                ->action(function (Media $record, array $data): void {
                    if (empty($data['new_file'])) {
                        Notification::make()
                            ->warning()
                            ->title('Brak pliku')
                            ->body('Nie wybrano nowego pliku')
                            ->send();

                        return;
                    }

                    $disk = Storage::disk('public');
                    $oldPath = $record->file_path;

                    // Usuń stary plik
                    if ($disk->exists($oldPath)) {
                        $disk->delete($oldPath);
                    }

                    // Zaktualizuj rekord
                    $filePath = $data['new_file'];
                    $mimeType = 'image/jpeg';
                    $fileSize = 0;
                    
                    if ($disk->exists($filePath)) {
                        /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
                        $mimeType = method_exists($disk, 'mimeType') 
                            ? ($disk->mimeType($filePath) ?? 'image/jpeg')
                            : 'image/jpeg';
                        $fileSize = method_exists($disk, 'size') 
                            ? $disk->size($filePath)
                            : 0;
                    }
                    
                    $record->update([
                        'file_path' => $filePath,
                        'file_name' => basename($filePath),
                        'mime_type' => $mimeType,
                        'size' => $fileSize,
                    ]);

                    // Zaktualizuj wymiary jeśli to obraz
                    if ($record->isImage()) {
                        $imageInfo = getimagesize($disk->path($data['new_file']));
                        if ($imageInfo) {
                            $record->update([
                                'width' => $imageInfo[0],
                                'height' => $imageInfo[1],
                            ]);
                        }
                    }

                    Notification::make()
                        ->success()
                        ->title('Obraz zaktualizowany')
                        ->body('Obraz został pomyślnie zastąpiony')
                        ->send();

                    $this->refreshFormData(['file_path', 'file_name', 'mime_type', 'size', 'width', 'height']);
                }),
            Actions\EditAction::make(),
        ];
    }
}

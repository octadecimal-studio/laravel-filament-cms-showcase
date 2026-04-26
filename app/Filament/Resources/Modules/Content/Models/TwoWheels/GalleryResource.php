<?php

declare(strict_types=1);

namespace App\Filament\Resources\Modules\Content\Models\TwoWheels;

use App\Modules\Core\Scopes\TenantScope;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use App\Filament\Resources\Modules\Content\Models\TwoWheels\GalleryResource\Pages\ListGallery;
use App\Filament\Resources\Modules\Content\Models\TwoWheels\GalleryResource\Pages;
use App\Modules\Content\Models\Media;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Zasób Galeria – hurtowy upload zdjęć bez opisów.
 * Model: Media, collection = 'gallery', tenant-scoped.
 */
final class GalleryResource extends Resource
{
    protected static ?string $model = Media::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-photo';

    protected static ?string $navigationLabel = 'Galeria';

    protected static ?string $modelLabel = 'Zdjęcie';

    protected static ?string $pluralModelLabel = 'Galeria';

    protected static ?int $navigationSort = 20;

    protected static bool $shouldRegisterNavigation = false;

    public const COLLECTION_GALLERY = 'gallery';

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->withoutGlobalScopes([TenantScope::class])
            ->where('collection', self::COLLECTION_GALLERY)
            ->where('mime_type', 'like', 'image/%');

        $user = auth()->user();
        if ($user && ! ($user->is_super_admin || $user->hasRole('super_admin'))) {
            $query->where('tenant_id', $user->tenant_id);
        }

        return $query->orderByDesc('created_at');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('file_path')
                    ->label('Zdjęcie')
                    ->disk('public')
                    ->height(80)
                    ->width(80)
                    ->square(),
                TextColumn::make('file_name')
                    ->label('Plik')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('size')
                    ->label('Rozmiar')
                    ->formatStateUsing(fn (int $state): string => number_format($state / 1024, 1) . ' KB')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Dodano')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                Action::make('crop')
                    ->label('Kadruj')
                    ->icon('heroicon-o-pencil-square')
                    ->visible(fn (): bool => true)
                    ->fillForm(fn (Media $record): array => [
                        'new_image' => $record->file_path,
                    ])
                    ->schema([
                        FileUpload::make('new_image')
                            ->label('Zdjęcie do kadrowania')
                            ->helperText('Wgraj nowe zdjęcie albo edytuj aktualne (otwórz w edytorze). Proporcje 1:1.')
                            ->required()
                            ->image()
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                            ->maxSize(5120)
                            ->disk('public')
                            ->directory('gallery')
                            ->visibility('public')
                            ->imagePreviewHeight('200')
                            ->imageEditor()
                            ->imageEditorAspectRatios(['1:1', null])
                            ->imageEditorEmptyFillColor('#ffffff')
                            ->imageEditorViewportWidth(400)
                            ->imageEditorViewportHeight(400)
                            ->openable()
                            ->columnSpanFull(),
                    ])
                    ->action(function (Media $record, array $data): void {
                        $newPath = $data['new_image'];
                        $disk = Storage::disk('public');
                        $oldPath = $record->file_path;

                        $record->file_name = basename($newPath);
                        $record->file_path = $newPath;
                        $record->mime_type = $disk->mimeType($newPath) ?? 'image/jpeg';
                        $record->size = $disk->size($newPath);
                        $record->alt_text = Str::beforeLast($record->file_name, '.');
                        $record->save();

                        if ($oldPath && $oldPath !== $newPath && $disk->exists($oldPath)) {
                            $disk->delete($oldPath);
                        }

                        Notification::make()
                            ->title('Zdjęcie zaktualizowane')
                            ->success()
                            ->send();
                    })
                    ->modalSubmitActionLabel('Zapisz'),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                DeleteBulkAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListGallery::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return true;
    }
}

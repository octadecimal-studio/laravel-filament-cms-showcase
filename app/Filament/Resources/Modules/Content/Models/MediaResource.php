<?php

declare(strict_types=1);

namespace App\Filament\Resources\Modules\Content\Models;

use App\Filament\Concerns\HasGlobalBulkActions;
use App\Filament\Concerns\RemembersTableSettings;
use App\Filament\Resources\Modules\Content\Models\MediaResource\Pages;
use App\Modules\Content\Models\Media;
use App\Modules\Content\Services\MediaService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;

/**
 * Filament Resource dla zarządzania mediami.
 */
final class MediaResource extends Resource
{
    use HasGlobalBulkActions;
    use RemembersTableSettings;

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    protected static ?string $model = Media::class;

    protected static ?string $navigationIcon = 'heroicon-o-photo';

    protected static ?string $navigationLabel = 'Media';

    protected static ?string $modelLabel = 'Medium';

    protected static ?string $pluralModelLabel = 'Media';

    protected static ?string $navigationGroup = 'Content';

    /**
     * Formularz edycji/tworzenia media.
     */
    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Upload pliku')
                    ->schema([
                        Forms\Components\FileUpload::make('file')
                            ->label('Plik')
                            ->required()
                            ->acceptedFileTypes(['image/*', 'application/pdf', 'video/*'])
                            ->maxSize(10240) // 10MB
                            ->disk('public')
                            ->directory('media')
                            ->visibility('public')
                            ->imagePreviewHeight('250')
                            ->imageEditor() // Edytor obrazów z kadrowaniem
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
                            ->columnSpanFull(),
                    ])
                    ->visible(fn (string $operation): bool => $operation === 'create'),

                Forms\Components\Section::make('Podgląd i edycja')
                    ->schema([
                        Forms\Components\ViewField::make('preview')
                            ->label('Aktualny obraz')
                            ->view('filament.forms.components.media-preview')
                            ->columnSpanFull()
                            ->visible(fn (?Media $record): bool => $record?->isImage() ?? false),

                        Forms\Components\FileUpload::make('new_file')
                            ->label('Zamień plik')
                            ->helperText('Opcjonalnie - wgraj nowy plik aby zastąpić obecny')
                            ->acceptedFileTypes(['image/*', 'application/pdf', 'video/*'])
                            ->maxSize(10240)
                            ->disk('public')
                            ->directory('media')
                            ->visibility('public')
                            ->imagePreviewHeight('250')
                            ->imageEditor()
                            ->imageEditorAspectRatios([
                                null,
                                '16:9',
                                '4:3',
                                '1:1',
                                '3:4',
                                '9:16',
                            ])
                            ->openable()
                            ->downloadable()
                            ->columnSpanFull(),
                    ])
                    ->visible(fn (string $operation): bool => $operation === 'edit'),

                Forms\Components\Section::make('Informacje o pliku')
                    ->schema([
                        Forms\Components\TextInput::make('file_name')
                            ->label('Nazwa pliku')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('mime_type')
                            ->label('Typ MIME')
                            ->disabled(),

                        Forms\Components\TextInput::make('size')
                            ->label('Rozmiar')
                            ->suffix('bytes')
                            ->disabled()
                            ->formatStateUsing(fn (?int $state): string => $state ? number_format($state) : '-'),

                        Forms\Components\TextInput::make('width')
                            ->label('Szerokość')
                            ->suffix('px')
                            ->disabled()
                            ->visible(fn (?Media $record): bool => $record?->isImage() ?? false),

                        Forms\Components\TextInput::make('height')
                            ->label('Wysokość')
                            ->suffix('px')
                            ->disabled()
                            ->visible(fn (?Media $record): bool => $record?->isImage() ?? false),
                    ])
                    ->columns(2)
                    ->collapsible()
                    ->collapsed()
                    ->visible(fn (string $operation): bool => $operation === 'edit'),

                Forms\Components\Section::make('Opis i metadane')
                    ->schema([
                        Forms\Components\TextInput::make('alt_text')
                            ->label('Alt text')
                            ->maxLength(255)
                            ->helperText('Tekst alternatywny dla obrazów (SEO)'),

                        Forms\Components\Textarea::make('caption')
                            ->label('Caption')
                            ->rows(3)
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('collection')
                            ->label('Kolekcja')
                            ->maxLength(255)
                            ->helperText('Kategoria/collection (np. gallery, documents)'),

                        Forms\Components\TagsInput::make('tags')
                            ->label('Tagi')
                            ->separator(',')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Status')
                    ->schema([
                        Forms\Components\Toggle::make('is_public')
                            ->label('Publiczny')
                            ->helperText('Dostępny bez autoryzacji'),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Aktywny')
                            ->default(true),
                    ])
                    ->columns(2),
            ]);
    }

    /**
     * Tabela listy mediów.
     */
    public static function table(Table $table): Table
    {
        // Zapamiętaj ustawienia w sesji (automatyczne klucze)
        $table->persistSortInSession()
            ->persistFiltersInSession()
            ->persistSearchInSession()
            ->persistColumnSearchesInSession();
        
        $table = $table
            ->columns([
                Tables\Columns\ImageColumn::make('file_path')
                    ->label('Preview')
                    ->disk(fn (Media $record): string => $record->disk)
                    ->height(60)
                    ->width(60)
                    ->visible(fn (?Media $record): bool => $record?->isImage() ?? false),

                Tables\Columns\TextColumn::make('file_name')
                    ->label('Nazwa pliku')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->limit(30),

                Tables\Columns\TextColumn::make('mime_type')
                    ->label('Typ')
                    ->badge()
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('size')
                    ->label('Rozmiar')
                    ->formatStateUsing(fn (?int $state): string => $state ? self::formatBytes($state) : '-')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('collection')
                    ->label('Kolekcja')
                    ->badge()
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\IconColumn::make('is_public')
                    ->label('Publiczny')
                    ->boolean()
                    ->toggleable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Aktywny')
                    ->boolean()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Utworzono')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('collection')
                    ->label('Kolekcja')
                    ->options(function (): array {
                        // Pobierz wszystkie unikalne kolekcje z bazy danych
                        $collections = Media::query()
                            ->whereNotNull('collection')
                            ->distinct()
                            ->pluck('collection', 'collection')
                            ->toArray();
                        
                        return $collections;
                    })
                    ->searchable()
                    ->native(false),

                Tables\Filters\TernaryFilter::make('is_public')
                    ->label('Publiczny')
                    ->placeholder('Wszystkie')
                    ->trueLabel('Tak')
                    ->falseLabel('Nie'),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Aktywny')
                    ->placeholder('Wszystkie')
                    ->trueLabel('Tak')
                    ->falseLabel('Nie'),

                Tables\Filters\Filter::make('images')
                    ->label('Tylko obrazy')
                    ->query(fn (Builder $query): Builder => $query->where('mime_type', 'like', 'image/%'))
                    ->toggle(),

                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\Action::make('preview')
                    ->label('Podgląd')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->modalHeading(fn (Media $record): string => $record->file_name)
                    ->modalContent(fn (Media $record) => view('filament.tables.actions.media-preview', ['record' => $record]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Zamknij')
                    ->visible(fn (?Media $record): bool => $record?->isImage() ?? false),
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('optimize')
                    ->label('Optymalizuj')
                    ->icon('heroicon-o-sparkles')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->action(function (Media $record): void {
                        $service = app(MediaService::class);
                        $service->optimize($record);
                    })
                    ->visible(fn (?Media $record): bool => $record?->isImage() ?? false),
                Tables\Actions\DeleteAction::make(),
                Tables\Actions\RestoreAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                    Tables\Actions\ForceDeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
        
        // Konfiguruj bulk actions
        $table = static::configureBulkActions($table);
        
        return $table;
    }
    
    /**
     * Infolist dla widoku (ViewRecord).
     */
    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Podgląd obrazu')
                    ->schema([
                        Infolists\Components\ImageEntry::make('file_path')
                            ->label('Obraz')
                            ->disk(fn (Media $record): string => $record->disk)
                            ->height(400)
                            ->width(600)
                            ->extraAttributes([
                                'class' => 'rounded-lg shadow-lg object-contain',
                            ])
                            ->visible(fn (Media $record): bool => $record->isImage()),
                    ])
                    ->visible(fn (Media $record): bool => $record->isImage())
                    ->collapsible(),

                Infolists\Components\Section::make('Informacje o pliku')
                    ->schema([
                        Infolists\Components\TextEntry::make('file_name')
                            ->label('Nazwa pliku')
                            ->weight('bold')
                            ->copyable(),

                        Infolists\Components\TextEntry::make('mime_type')
                            ->label('Typ MIME')
                            ->badge(),

                        Infolists\Components\TextEntry::make('size')
                            ->label('Rozmiar')
                            ->formatStateUsing(fn (?int $state): string => $state ? self::formatBytes($state) : '-'),

                        Infolists\Components\TextEntry::make('width')
                            ->label('Szerokość')
                            ->suffix(' px')
                            ->visible(fn (Media $record): bool => $record->isImage() && $record->width !== null),

                        Infolists\Components\TextEntry::make('height')
                            ->label('Wysokość')
                            ->suffix(' px')
                            ->visible(fn (Media $record): bool => $record->isImage() && $record->height !== null),

                        Infolists\Components\TextEntry::make('file_path')
                            ->label('URL')
                            ->formatStateUsing(fn (Media $record): string => $record->getUrl())
                            ->url(fn (Media $record): string => $record->getUrl())
                            ->openUrlInNewTab()
                            ->copyable()
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->collapsible(),

                Infolists\Components\Section::make('Opis i metadane')
                    ->schema([
                        Infolists\Components\TextEntry::make('alt_text')
                            ->label('Alt text')
                            ->placeholder('Brak'),

                        Infolists\Components\TextEntry::make('caption')
                            ->label('Caption')
                            ->placeholder('Brak')
                            ->columnSpanFull(),

                        Infolists\Components\TextEntry::make('collection')
                            ->label('Kolekcja')
                            ->badge()
                            ->placeholder('Brak'),

                        Infolists\Components\TextEntry::make('tags')
                            ->label('Tagi')
                            ->badge()
                            ->separator(',')
                            ->placeholder('Brak tagów')
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->collapsible(),

                Infolists\Components\Section::make('Status')
                    ->schema([
                        Infolists\Components\IconEntry::make('is_public')
                            ->label('Publiczny')
                            ->boolean(),

                        Infolists\Components\IconEntry::make('is_active')
                            ->label('Aktywny')
                            ->boolean(),

                        Infolists\Components\TextEntry::make('created_at')
                            ->label('Utworzono')
                            ->dateTime('d.m.Y H:i'),

                        Infolists\Components\TextEntry::make('updated_at')
                            ->label('Zaktualizowano')
                            ->dateTime('d.m.Y H:i'),
                    ])
                    ->columns(2)
                    ->collapsible()
                    ->collapsed(),
            ]);
    }

    /**
     * Relacje.
     */
    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    /**
     * Strony Resource.
     */
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMedia::route('/'),
            'create' => Pages\CreateMedia::route('/create'),
            'view' => Pages\ViewMedia::route('/{record}'),
            'edit' => Pages\EditMedia::route('/{record}/edit'),
        ];
    }

    /**
     * Query builder z domyślnymi filtrami.
     *
     * @return Builder<Media>
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    /**
     * Formatuj bajty do czytelnej formy.
     */
    private static function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, $precision).' '.$units[$i];
    }
}

<?php

declare(strict_types=1);

namespace App\Filament\Resources\Modules\Content\Models;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\ViewField;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Toggle;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\IconEntry;
use App\Filament\Resources\Modules\Content\Models\MediaResource\Pages\ListMedia;
use App\Filament\Resources\Modules\Content\Models\MediaResource\Pages\CreateMedia;
use App\Filament\Resources\Modules\Content\Models\MediaResource\Pages\ViewMedia;
use App\Filament\Resources\Modules\Content\Models\MediaResource\Pages\EditMedia;
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

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-photo';

    protected static ?string $navigationLabel = 'Media';

    protected static ?string $modelLabel = 'Medium';

    protected static ?string $pluralModelLabel = 'Media';

    protected static string | \UnitEnum | null $navigationGroup = 'Content';

    /**
     * Formularz edycji/tworzenia media.
     */
    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Upload pliku')
                    ->schema([
                        FileUpload::make('file')
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

                Section::make('Podgląd i edycja')
                    ->schema([
                        ViewField::make('preview')
                            ->label('Aktualny obraz')
                            ->view('filament.forms.components.media-preview')
                            ->columnSpanFull()
                            ->visible(fn (?Media $record): bool => $record?->isImage() ?? false),

                        FileUpload::make('new_file')
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

                Section::make('Informacje o pliku')
                    ->schema([
                        TextInput::make('file_name')
                            ->label('Nazwa pliku')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('mime_type')
                            ->label('Typ MIME')
                            ->disabled(),

                        TextInput::make('size')
                            ->label('Rozmiar')
                            ->suffix('bytes')
                            ->disabled()
                            ->formatStateUsing(fn (?int $state): string => $state ? number_format($state) : '-'),

                        TextInput::make('width')
                            ->label('Szerokość')
                            ->suffix('px')
                            ->disabled()
                            ->visible(fn (?Media $record): bool => $record?->isImage() ?? false),

                        TextInput::make('height')
                            ->label('Wysokość')
                            ->suffix('px')
                            ->disabled()
                            ->visible(fn (?Media $record): bool => $record?->isImage() ?? false),
                    ])
                    ->columns(2)
                    ->collapsible()
                    ->collapsed()
                    ->visible(fn (string $operation): bool => $operation === 'edit'),

                Section::make('Opis i metadane')
                    ->schema([
                        TextInput::make('alt_text')
                            ->label('Alt text')
                            ->maxLength(255)
                            ->helperText('Tekst alternatywny dla obrazów (SEO)'),

                        Textarea::make('caption')
                            ->label('Caption')
                            ->rows(3)
                            ->columnSpanFull(),

                        TextInput::make('collection')
                            ->label('Kolekcja')
                            ->maxLength(255)
                            ->helperText('Kategoria/collection (np. gallery, documents)'),

                        TagsInput::make('tags')
                            ->label('Tagi')
                            ->separator(',')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Section::make('Status')
                    ->schema([
                        Toggle::make('is_public')
                            ->label('Publiczny')
                            ->helperText('Dostępny bez autoryzacji'),

                        Toggle::make('is_active')
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
                ImageColumn::make('file_path')
                    ->label('Preview')
                    ->disk(fn (Media $record): string => $record->disk)
                    ->height(60)
                    ->width(60)
                    ->visible(fn (?Media $record): bool => $record?->isImage() ?? false),

                TextColumn::make('file_name')
                    ->label('Nazwa pliku')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->limit(30),

                TextColumn::make('mime_type')
                    ->label('Typ')
                    ->badge()
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('size')
                    ->label('Rozmiar')
                    ->formatStateUsing(fn (?int $state): string => $state ? self::formatBytes($state) : '-')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('collection')
                    ->label('Kolekcja')
                    ->badge()
                    ->searchable()
                    ->toggleable(),

                IconColumn::make('is_public')
                    ->label('Publiczny')
                    ->boolean()
                    ->toggleable(),

                IconColumn::make('is_active')
                    ->label('Aktywny')
                    ->boolean()
                    ->toggleable(),

                TextColumn::make('created_at')
                    ->label('Utworzono')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('collection')
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

                TernaryFilter::make('is_public')
                    ->label('Publiczny')
                    ->placeholder('Wszystkie')
                    ->trueLabel('Tak')
                    ->falseLabel('Nie'),

                TernaryFilter::make('is_active')
                    ->label('Aktywny')
                    ->placeholder('Wszystkie')
                    ->trueLabel('Tak')
                    ->falseLabel('Nie'),

                Filter::make('images')
                    ->label('Tylko obrazy')
                    ->query(fn (Builder $query): Builder => $query->where('mime_type', 'like', 'image/%'))
                    ->toggle(),

                TrashedFilter::make(),
            ])
            ->recordActions([
                Action::make('preview')
                    ->label('Podgląd')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->modalHeading(fn (Media $record): string => $record->file_name)
                    ->modalContent(fn (Media $record) => view('filament.tables.actions.media-preview', ['record' => $record]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Zamknij')
                    ->visible(fn (?Media $record): bool => $record?->isImage() ?? false),
                ViewAction::make(),
                EditAction::make(),
                Action::make('optimize')
                    ->label('Optymalizuj')
                    ->icon('heroicon-o-sparkles')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->action(function (Media $record): void {
                        $service = app(MediaService::class);
                        $service->optimize($record);
                    })
                    ->visible(fn (?Media $record): bool => $record?->isImage() ?? false),
                DeleteAction::make(),
                RestoreAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                    ForceDeleteBulkAction::make(),
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
    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Podgląd obrazu')
                    ->schema([
                        ImageEntry::make('file_path')
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

                Section::make('Informacje o pliku')
                    ->schema([
                        TextEntry::make('file_name')
                            ->label('Nazwa pliku')
                            ->weight('bold')
                            ->copyable(),

                        TextEntry::make('mime_type')
                            ->label('Typ MIME')
                            ->badge(),

                        TextEntry::make('size')
                            ->label('Rozmiar')
                            ->formatStateUsing(fn (?int $state): string => $state ? self::formatBytes($state) : '-'),

                        TextEntry::make('width')
                            ->label('Szerokość')
                            ->suffix(' px')
                            ->visible(fn (Media $record): bool => $record->isImage() && $record->width !== null),

                        TextEntry::make('height')
                            ->label('Wysokość')
                            ->suffix(' px')
                            ->visible(fn (Media $record): bool => $record->isImage() && $record->height !== null),

                        TextEntry::make('file_path')
                            ->label('URL')
                            ->formatStateUsing(fn (Media $record): string => $record->getUrl())
                            ->url(fn (Media $record): string => $record->getUrl())
                            ->openUrlInNewTab()
                            ->copyable()
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->collapsible(),

                Section::make('Opis i metadane')
                    ->schema([
                        TextEntry::make('alt_text')
                            ->label('Alt text')
                            ->placeholder('Brak'),

                        TextEntry::make('caption')
                            ->label('Caption')
                            ->placeholder('Brak')
                            ->columnSpanFull(),

                        TextEntry::make('collection')
                            ->label('Kolekcja')
                            ->badge()
                            ->placeholder('Brak'),

                        TextEntry::make('tags')
                            ->label('Tagi')
                            ->badge()
                            ->separator(',')
                            ->placeholder('Brak tagów')
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->collapsible(),

                Section::make('Status')
                    ->schema([
                        IconEntry::make('is_public')
                            ->label('Publiczny')
                            ->boolean(),

                        IconEntry::make('is_active')
                            ->label('Aktywny')
                            ->boolean(),

                        TextEntry::make('created_at')
                            ->label('Utworzono')
                            ->dateTime('d.m.Y H:i'),

                        TextEntry::make('updated_at')
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
            'index' => ListMedia::route('/'),
            'create' => CreateMedia::route('/create'),
            'view' => ViewMedia::route('/{record}'),
            'edit' => EditMedia::route('/{record}/edit'),
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

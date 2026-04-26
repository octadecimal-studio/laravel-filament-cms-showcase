<?php

declare(strict_types=1);

namespace App\Filament\Resources\Modules\Content\Models\TwoWheels;

use App\Modules\Core\Scopes\TenantScope;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Placeholder;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Schemas\Components\Livewire;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Forms\Components\DateTimePicker;
use Filament\Actions\Action;
use App\Modules\Core\Models\Tenant;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use App\Filament\Resources\Modules\Content\Models\TwoWheels\MotorcycleResource\Pages\ListMotorcycles;
use App\Filament\Resources\Modules\Content\Models\TwoWheels\MotorcycleResource\Pages\CreateMotorcycle;
use App\Filament\Resources\Modules\Content\Models\TwoWheels\MotorcycleResource\Pages\EditMotorcycle;
use App\Filament\Resources\Modules\Content\Models\TwoWheels\MotorcycleResource\Pages;
use App\Filament\Resources\Modules\Content\Models\TwoWheels\MotorcycleResource\RelationManagers;
use App\Modules\Content\Models\TwoWheels\Motorcycle;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

/**
 * Filament Resource dla zarządzania motocyklami MotoRent Demo.
 */
final class MotorcycleResource extends Resource
{
    protected static ?string $model = Motorcycle::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-bolt';

    protected static ?string $navigationLabel = 'Motocykle';

    protected static ?string $modelLabel = 'Motocykl';

    protected static ?string $pluralModelLabel = 'Motocykle';

    protected static ?int $navigationSort = 50;

    /**
     * Filtruj dane po tenant_id.
     */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
                TenantScope::class,
            ]);

        // Filtruj po tenant_id dla nie-super adminów
        $user = auth()->user();
        if ($user && !($user->is_super_admin || $user->hasRole('super_admin'))) {
            $query->where('tenant_id', $user->tenant_id);
        }

        return $query;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Podstawowe informacje')
                    ->schema([
                        TextInput::make('name')
                            ->label('Nazwa')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),

                        TextInput::make('slug')
                            ->label('Slug')
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->helperText('URL-friendly identyfikator (generowany automatycznie)'),

                        Select::make('brand_id')
                            ->label('Marka')
                            ->relationship('brand', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->createOptionForm([
                                TextInput::make('name')
                                    ->label('Nazwa marki')
                                    ->required(),
                            ]),

                        Select::make('category_id')
                            ->label('Kategoria')
                            ->relationship('category', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->createOptionForm([
                                TextInput::make('name')
                                    ->label('Nazwa kategorii')
                                    ->required(),
                            ]),
                    ])
                    ->columns(2),

                Section::make('Główny obraz')
                    ->schema([
                        // Podgląd aktualnego obrazu (tylko w edycji)
                        Placeholder::make('current_image_preview')
                            ->label('Aktualny obraz')
                            ->content(function (?Motorcycle $record): Htmlable {
                                if (!$record || !$record->mainImage) {
                                    return new HtmlString('<span class="text-gray-500">Brak obrazu</span>');
                                }
                                $url = asset('storage/' . $record->mainImage->file_path);
                                return new HtmlString(
                                    '<img src="' . $url . '" alt="' . e($record->mainImage->file_name) . '" 
                                         class="max-h-48 rounded-lg shadow-md object-cover" />'
                                );
                            })
                            ->visible(fn (string $operation): bool => $operation === 'edit')
                            ->columnSpanFull(),

                        // Upload nowego obrazu z edytorem
                        FileUpload::make('new_main_image')
                            ->label(fn (string $operation): string => $operation === 'create' ? 'Wgraj obraz' : 'Podmień obraz')
                            ->helperText('Wgraj obraz lub użyj edytora do kadrowania. Obsługiwane formaty: JPG, PNG, WebP.')
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                            ->maxSize(5120) // 5MB
                            ->disk('public')
                            ->directory('motorcycles')
                            ->visibility('public')
                            ->imagePreviewHeight('200')
                            ->imageEditor()
                            ->imageEditorAspectRatios([
                                null, // Dowolny
                                '16:9',
                                '4:3',
                                '3:2',
                                '1:1',
                            ])
                            ->imageEditorEmptyFillColor('#ffffff')
                            ->imageEditorViewportWidth(1920)
                            ->imageEditorViewportHeight(1080)
                            ->openable()
                            ->downloadable()
                            ->columnSpanFull(),

                        // Ukryty select dla kompatybilności z istniejącymi danymi
                        Hidden::make('main_image_id'),
                    ]),

                Section::make('Galeria zdjęć')
                    ->schema([
                        Livewire::make('motorcycle-gallery-manager')
                            ->lazy()
                            ->visible(fn (string $operation): bool => $operation === 'edit')
                            ->columnSpanFull(),

                        // Upload galerii przy tworzeniu (Livewire komponent wymaga istniejącego rekordu)
                        FileUpload::make('new_gallery_images')
                            ->label('Wgraj zdjęcia do galerii')
                            ->helperText('Możesz wybrać wiele zdjęć naraz.')
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                            ->maxSize(5120)
                            ->disk('public')
                            ->directory('motorcycles/gallery')
                            ->visibility('public')
                            ->multiple()
                            ->reorderable()
                            ->imagePreviewHeight('100')
                            ->columnSpanFull()
                            ->visible(fn (string $operation): bool => $operation === 'create'),
                    ])
                    ->collapsible(),

                Section::make('Specyfikacje')
                    ->schema([
                        TextInput::make('engine_capacity')
                            ->label('Pojemność silnika')
                            ->numeric()
                            ->suffix(' cc')
                            ->required(),

                        TextInput::make('year')
                            ->label('Rok produkcji')
                            ->numeric()
                            ->minValue(1900)
                            ->maxValue(now()->year + 1)
                            ->required(),

                        KeyValue::make('specifications')
                            ->label('Specyfikacje')
                            ->keyLabel('Parametr')
                            ->valueLabel('Wartość')
                            ->columnSpanFull()
                            ->helperText('Dodatkowe specyfikacje (np. Moc: 100 KM, Waga: 200 kg)'),
                    ])
                    ->columns(2),

                Section::make('Cennik')
                    ->schema([
                        TextInput::make('price_per_day')
                            ->label('Cena za dzień')
                            ->numeric()
                            ->prefix('PLN')
                            ->required(),

                        TextInput::make('price_per_week')
                            ->label('Cena za tydzień')
                            ->numeric()
                            ->prefix('PLN')
                            ->required(),

                        TextInput::make('price_per_month')
                            ->label('Cena za miesiąc')
                            ->numeric()
                            ->prefix('PLN')
                            ->required(),

                        TextInput::make('deposit')
                            ->label('Kaucja')
                            ->numeric()
                            ->prefix('PLN')
                            ->required(),
                    ])
                    ->columns(2),

                Section::make('Opis')
                    ->schema([
                        RichEditor::make('description')
                            ->label('Opis')
                            ->columnSpanFull()
                            ->toolbarButtons([
                                'bold',
                                'italic',
                                'underline',
                                'bulletList',
                                'orderedList',
                                'link',
                            ]),
                    ]),

                Section::make('Status')
                    ->schema([
                        Toggle::make('available')
                            ->label('Dostępny')
                            ->default(true),

                        Toggle::make('featured')
                            ->label('Wyróżniony')
                            ->default(false),

                        Toggle::make('published')
                            ->label('Opublikowany')
                            ->default(false)
                            ->live()
                            ->afterStateUpdated(function (Set $set, Get $get, bool $state): void {
                                if ($state && !$get('published_at')) {
                                    $set('published_at', now()->format('Y-m-d H:i:s'));
                                }
                            }),

                        DateTimePicker::make('published_at')
                            ->label('Data publikacji')
                            ->visible(fn (Get $get): bool => $get('published') === true),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->headerActions([
                Action::make('view_api')
                    ->label('Zobacz API')
                    ->icon('heroicon-o-code-bracket')
                    ->url(function () {
                        $u = auth()->user();
                        $base = url('/api/motorent/motorcycles');
                        if ($u?->isSuperAdmin()) {
                            return $base;
                        }
                        return $base . '?tenant_id=' . ($u?->tenant_id ?? Tenant::where('slug', 'demo-studio')->where('is_active', true)->value('id') ?? '');
                    })
                    ->openUrlInNewTab()
                    ->color('info'),
            ])
            ->columns([
                TextColumn::make('name')
                    ->label('Nazwa')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('brand.name')
                    ->label('Marka')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('category.name')
                    ->label('Kategoria')
                    ->badge()
                    ->searchable()
                    ->sortable(),

                TextColumn::make('engine_capacity')
                    ->label('Pojemność')
                    ->suffix(' cc')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('year')
                    ->label('Rok')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('price_per_day')
                    ->label('Cena/dzień')
                    ->money('PLN')
                    ->sortable(),

                IconColumn::make('available')
                    ->label('Dostępny')
                    ->boolean(),

                IconColumn::make('featured')
                    ->label('Wyróżniony')
                    ->boolean()
                    ->toggleable(),

                IconColumn::make('published')
                    ->label('Opublikowany')
                    ->boolean()
                    ->toggleable(),

                TextColumn::make('created_at')
                    ->label('Utworzono')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('brand_id')
                    ->label('Marka')
                    ->relationship('brand', 'name')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('category_id')
                    ->label('Kategoria')
                    ->relationship('category', 'name')
                    ->searchable()
                    ->preload(),

                TernaryFilter::make('available')
                    ->label('Dostępny')
                    ->placeholder('Wszystkie')
                    ->trueLabel('Tak')
                    ->falseLabel('Nie'),

                TernaryFilter::make('published')
                    ->label('Opublikowany')
                    ->placeholder('Wszystkie')
                    ->trueLabel('Tak')
                    ->falseLabel('Nie'),

                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
                RestoreAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMotorcycles::route('/'),
            'create' => CreateMotorcycle::route('/create'),
            'edit' => EditMotorcycle::route('/{record}/edit'),
        ];
    }
}

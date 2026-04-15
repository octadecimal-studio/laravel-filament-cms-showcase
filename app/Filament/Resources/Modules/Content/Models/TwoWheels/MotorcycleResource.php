<?php

declare(strict_types=1);

namespace App\Filament\Resources\Modules\Content\Models\TwoWheels;

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

    protected static ?string $navigationIcon = 'heroicon-o-bolt';

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
                \App\Modules\Core\Scopes\TenantScope::class,
            ]);

        // Filtruj po tenant_id dla nie-super adminów
        $user = auth()->user();
        if ($user && !($user->is_super_admin || $user->hasRole('super_admin'))) {
            $query->where('tenant_id', $user->tenant_id);
        }

        return $query;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Podstawowe informacje')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nazwa')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('slug')
                            ->label('Slug')
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->helperText('URL-friendly identyfikator (generowany automatycznie)'),

                        Forms\Components\Select::make('brand_id')
                            ->label('Marka')
                            ->relationship('brand', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->createOptionForm([
                                Forms\Components\TextInput::make('name')
                                    ->label('Nazwa marki')
                                    ->required(),
                            ]),

                        Forms\Components\Select::make('category_id')
                            ->label('Kategoria')
                            ->relationship('category', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->createOptionForm([
                                Forms\Components\TextInput::make('name')
                                    ->label('Nazwa kategorii')
                                    ->required(),
                            ]),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Główny obraz')
                    ->schema([
                        // Podgląd aktualnego obrazu (tylko w edycji)
                        Forms\Components\Placeholder::make('current_image_preview')
                            ->label('Aktualny obraz')
                            ->content(function (?Motorcycle $record): \Illuminate\Contracts\Support\Htmlable {
                                if (!$record || !$record->mainImage) {
                                    return new \Illuminate\Support\HtmlString('<span class="text-gray-500">Brak obrazu</span>');
                                }
                                $url = asset('storage/' . $record->mainImage->file_path);
                                return new \Illuminate\Support\HtmlString(
                                    '<img src="' . $url . '" alt="' . e($record->mainImage->file_name) . '" 
                                         class="max-h-48 rounded-lg shadow-md object-cover" />'
                                );
                            })
                            ->visible(fn (string $operation): bool => $operation === 'edit')
                            ->columnSpanFull(),

                        // Upload nowego obrazu z edytorem
                        Forms\Components\FileUpload::make('new_main_image')
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
                        Forms\Components\Hidden::make('main_image_id'),
                    ]),

                Forms\Components\Section::make('Galeria zdjęć')
                    ->schema([
                        Forms\Components\Livewire::make('motorcycle-gallery-manager')
                            ->lazy()
                            ->visible(fn (string $operation): bool => $operation === 'edit')
                            ->columnSpanFull(),

                        // Upload galerii przy tworzeniu (Livewire komponent wymaga istniejącego rekordu)
                        Forms\Components\FileUpload::make('new_gallery_images')
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

                Forms\Components\Section::make('Specyfikacje')
                    ->schema([
                        Forms\Components\TextInput::make('engine_capacity')
                            ->label('Pojemność silnika')
                            ->numeric()
                            ->suffix(' cc')
                            ->required(),

                        Forms\Components\TextInput::make('year')
                            ->label('Rok produkcji')
                            ->numeric()
                            ->minValue(1900)
                            ->maxValue(now()->year + 1)
                            ->required(),

                        Forms\Components\KeyValue::make('specifications')
                            ->label('Specyfikacje')
                            ->keyLabel('Parametr')
                            ->valueLabel('Wartość')
                            ->columnSpanFull()
                            ->helperText('Dodatkowe specyfikacje (np. Moc: 100 KM, Waga: 200 kg)'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Cennik')
                    ->schema([
                        Forms\Components\TextInput::make('price_per_day')
                            ->label('Cena za dzień')
                            ->numeric()
                            ->prefix('PLN')
                            ->required(),

                        Forms\Components\TextInput::make('price_per_week')
                            ->label('Cena za tydzień')
                            ->numeric()
                            ->prefix('PLN')
                            ->required(),

                        Forms\Components\TextInput::make('price_per_month')
                            ->label('Cena za miesiąc')
                            ->numeric()
                            ->prefix('PLN')
                            ->required(),

                        Forms\Components\TextInput::make('deposit')
                            ->label('Kaucja')
                            ->numeric()
                            ->prefix('PLN')
                            ->required(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Opis')
                    ->schema([
                        Forms\Components\RichEditor::make('description')
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

                Forms\Components\Section::make('Status')
                    ->schema([
                        Forms\Components\Toggle::make('available')
                            ->label('Dostępny')
                            ->default(true),

                        Forms\Components\Toggle::make('featured')
                            ->label('Wyróżniony')
                            ->default(false),

                        Forms\Components\Toggle::make('published')
                            ->label('Opublikowany')
                            ->default(false)
                            ->live()
                            ->afterStateUpdated(function (Forms\Set $set, Forms\Get $get, bool $state): void {
                                if ($state && !$get('published_at')) {
                                    $set('published_at', now()->format('Y-m-d H:i:s'));
                                }
                            }),

                        Forms\Components\DateTimePicker::make('published_at')
                            ->label('Data publikacji')
                            ->visible(fn (Forms\Get $get): bool => $get('published') === true),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->headerActions([
                Tables\Actions\Action::make('view_api')
                    ->label('Zobacz API')
                    ->icon('heroicon-o-code-bracket')
                    ->url(function () {
                        $u = auth()->user();
                        $base = url('/api/motorent/motorcycles');
                        if ($u?->isSuperAdmin()) {
                            return $base;
                        }
                        return $base . '?tenant_id=' . ($u?->tenant_id ?? \App\Modules\Core\Models\Tenant::where('slug', 'demo-studio')->where('is_active', true)->value('id') ?? '');
                    })
                    ->openUrlInNewTab()
                    ->color('info'),
            ])
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nazwa')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('brand.name')
                    ->label('Marka')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('category.name')
                    ->label('Kategoria')
                    ->badge()
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('engine_capacity')
                    ->label('Pojemność')
                    ->suffix(' cc')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('year')
                    ->label('Rok')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('price_per_day')
                    ->label('Cena/dzień')
                    ->money('PLN')
                    ->sortable(),

                Tables\Columns\IconColumn::make('available')
                    ->label('Dostępny')
                    ->boolean(),

                Tables\Columns\IconColumn::make('featured')
                    ->label('Wyróżniony')
                    ->boolean()
                    ->toggleable(),

                Tables\Columns\IconColumn::make('published')
                    ->label('Opublikowany')
                    ->boolean()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Utworzono')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('brand_id')
                    ->label('Marka')
                    ->relationship('brand', 'name')
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('category_id')
                    ->label('Kategoria')
                    ->relationship('category', 'name')
                    ->searchable()
                    ->preload(),

                Tables\Filters\TernaryFilter::make('available')
                    ->label('Dostępny')
                    ->placeholder('Wszystkie')
                    ->trueLabel('Tak')
                    ->falseLabel('Nie'),

                Tables\Filters\TernaryFilter::make('published')
                    ->label('Opublikowany')
                    ->placeholder('Wszystkie')
                    ->trueLabel('Tak')
                    ->falseLabel('Nie'),

                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
                Tables\Actions\RestoreAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
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
            'index' => Pages\ListMotorcycles::route('/'),
            'create' => Pages\CreateMotorcycle::route('/create'),
            'edit' => Pages\EditMotorcycle::route('/{record}/edit'),
        ];
    }
}

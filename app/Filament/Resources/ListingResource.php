<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Concerns\HasGlobalBulkActions;
use App\Filament\Concerns\HasNavigationPermission;
use App\Filament\Concerns\RemembersTableSettings;
use App\Filament\Resources\ListingResource\Pages;
use App\Models\Listing;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

/**
 * Filament Resource dla ogłoszeń (leadów).
 */
class ListingResource extends Resource
{
    use HasGlobalBulkActions;
    use HasNavigationPermission;
    use RemembersTableSettings;

    /**
     * Prefix uprawnień dla tego zasobu.
     */
    protected static string $permissionPrefix = 'listings';

    protected static ?string $model = Listing::class;

    protected static ?string $navigationIcon = 'heroicon-o-megaphone';

    protected static ?string $navigationLabel = 'Ogłoszenia';

    protected static ?string $modelLabel = 'Ogłoszenie';

    protected static ?string $pluralModelLabel = 'Ogłoszenia';

    protected static ?string $navigationGroup = 'Lead Generation';

    protected static ?int $navigationSort = 1;

    // Widoczność w nawigacji jest zarządzana przez HasNavigationPermission trait
    // na podstawie uprawnienia 'listings.view_any'

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Źródło')
                    ->schema([
                        Forms\Components\Select::make('platform')
                            ->label('Platforma')
                            ->options([
                                'useme' => 'Useme.com',
                                'oferteo' => 'Oferteo.pl',
                                'fixly' => 'Fixly.pl',
                                'direct' => 'Bezpośrednie',
                                'other' => 'Inne',
                            ])
                            ->default('useme')
                            ->required(),

                        Forms\Components\TextInput::make('external_id')
                            ->label('ID zewnętrzne')
                            ->maxLength(100),

                        Forms\Components\TextInput::make('url')
                            ->label('URL ogłoszenia')
                            ->url()
                            ->required()
                            ->maxLength(500)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Treść ogłoszenia')
                    ->schema([
                        Forms\Components\TextInput::make('title')
                            ->label('Tytuł')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),

                        Forms\Components\RichEditor::make('description')
                            ->label('Opis')
                            ->columnSpanFull(),
                    ]),

                Forms\Components\Section::make('Budżet i termin')
                    ->schema([
                        Forms\Components\TextInput::make('budget_min')
                            ->label('Budżet min')
                            ->numeric()
                            ->prefix('PLN'),

                        Forms\Components\TextInput::make('budget_max')
                            ->label('Budżet max')
                            ->numeric()
                            ->prefix('PLN'),

                        Forms\Components\Select::make('currency')
                            ->label('Waluta')
                            ->options([
                                'PLN' => 'PLN',
                                'EUR' => 'EUR',
                                'USD' => 'USD',
                            ])
                            ->default('PLN'),

                        Forms\Components\DatePicker::make('deadline')
                            ->label('Termin realizacji'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Klient')
                    ->schema([
                        Forms\Components\TextInput::make('client_name')
                            ->label('Nazwa klienta')
                            ->maxLength(255),

                        Forms\Components\TextInput::make('client_location')
                            ->label('Lokalizacja')
                            ->maxLength(255),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Status')
                    ->schema([
                        Forms\Components\Select::make('status')
                            ->label('Status')
                            ->options([
                                'new' => 'Nowe',
                                'reviewing' => 'Analizuję',
                                'spec_sent' => 'Wysłano spec',
                                'interested' => 'Zainteresowany',
                                'not_interested' => 'Niezainteresowany',
                                'won' => 'Wygrane',
                                'lost' => 'Przegrane',
                            ])
                            ->default('new')
                            ->required(),

                        Forms\Components\Select::make('priority')
                            ->label('Priorytet')
                            ->options([
                                'low' => 'Niski',
                                'medium' => 'Średni',
                                'high' => 'Wysoki',
                            ])
                            ->default('medium'),

                        Forms\Components\DateTimePicker::make('found_at')
                            ->label('Znalezione'),

                        Forms\Components\DateTimePicker::make('expires_at')
                            ->label('Wygasa'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Notatki')
                    ->schema([
                        Forms\Components\Textarea::make('notes')
                            ->label('Notatki')
                            ->rows(4)
                            ->columnSpanFull(),
                    ])
                    ->collapsible(),
            ]);
    }

    public static function table(Table $table): Table
    {
        // Zapamiętaj ustawienia w sesji (automatyczne klucze)
        $table->persistSortInSession()
            ->persistFiltersInSession()
            ->persistSearchInSession()
            ->persistColumnSearchesInSession();
        
        $table = $table
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->label('Tytuł')
                    ->searchable()
                    ->limit(50)
                    ->tooltip(fn ($record) => $record->title),

                Tables\Columns\BadgeColumn::make('platform')
                    ->label('Platforma')
                    ->colors([
                        'success' => 'useme',
                        'info' => 'oferteo',
                        'warning' => 'fixly',
                        'gray' => 'direct',
                    ]),

                Tables\Columns\TextColumn::make('budget_range')
                    ->label('Budżet')
                    ->getStateUsing(fn ($record) => $record->budget_range),

                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->colors([
                        'gray' => 'new',
                        'info' => 'reviewing',
                        'warning' => 'spec_sent',
                        'primary' => 'interested',
                        'secondary' => 'not_interested',
                        'success' => 'won',
                        'danger' => 'lost',
                    ])
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'new' => 'Nowe',
                        'reviewing' => 'Analizuję',
                        'spec_sent' => 'Wysłano spec',
                        'interested' => 'Zainteresowany',
                        'not_interested' => 'Niezaint.',
                        'won' => 'Wygrane',
                        'lost' => 'Przegrane',
                        default => $state,
                    }),

                Tables\Columns\BadgeColumn::make('priority')
                    ->label('Priorytet')
                    ->colors([
                        'gray' => 'low',
                        'warning' => 'medium',
                        'danger' => 'high',
                    ])
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'low' => 'Niski',
                        'medium' => 'Średni',
                        'high' => 'Wysoki',
                        default => $state,
                    }),

                Tables\Columns\TextColumn::make('client_name')
                    ->label('Klient')
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('deadline')
                    ->label('Termin')
                    ->date('d.m.Y')
                    ->sortable()
                    ->color(fn ($record) => $record->deadline?->isPast() ? 'danger' : null),

                Tables\Columns\TextColumn::make('found_at')
                    ->label('Znalezione')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Utworzono')
                    ->dateTime('d.m.Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->multiple()
                    ->options([
                        'new' => 'Nowe',
                        'reviewing' => 'Analizuję',
                        'spec_sent' => 'Wysłano spec',
                        'interested' => 'Zainteresowany',
                        'won' => 'Wygrane',
                        'lost' => 'Przegrane',
                    ]),

                Tables\Filters\SelectFilter::make('platform')
                    ->label('Platforma')
                    ->options([
                        'useme' => 'Useme.com',
                        'oferteo' => 'Oferteo.pl',
                        'fixly' => 'Fixly.pl',
                        'direct' => 'Bezpośrednie',
                    ]),

                Tables\Filters\SelectFilter::make('priority')
                    ->label('Priorytet')
                    ->options([
                        'low' => 'Niski',
                        'medium' => 'Średni',
                        'high' => 'Wysoki',
                    ]),

                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\Action::make('visit')
                    ->label('Otwórz')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn ($record) => $record->url)
                    ->openUrlInNewTab(),

                Tables\Actions\Action::make('create_order')
                    ->label('Utwórz zlecenie')
                    ->icon('heroicon-o-plus')
                    ->color('success')
                    ->visible(fn ($record) => $record->status === 'interested')
                    ->url(fn ($record) => OrderResource::getUrl('create', [
                        'listing_id' => $record->id,
                    ])),

                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\ForceDeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                ]),
            ])
            ->defaultSort('found_at', 'desc');
        
        // Konfiguruj bulk actions
        $table = static::configureBulkActions($table);
        
        return $table;
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
            'index' => Pages\ListListings::route('/'),
            'create' => Pages\CreateListing::route('/create'),
            'view' => Pages\ViewListing::route('/{record}'),
            'edit' => Pages\EditListing::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) static::getModel()::where('status', 'new')->count() ?: null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'info';
    }
}

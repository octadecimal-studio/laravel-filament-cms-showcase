<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Concerns\HasGlobalBulkActions;
use App\Filament\Concerns\HasNavigationPermission;
use App\Filament\Concerns\RemembersTableSettings;
use App\Filament\Resources\OrderResource\Pages;
use App\Models\Order;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

/**
 * Filament Resource dla zleceń.
 */
class OrderResource extends Resource
{
    use HasGlobalBulkActions;
    use HasNavigationPermission;
    use RemembersTableSettings;

    /**
     * Prefix uprawnień dla tego zasobu.
     */
    protected static string $permissionPrefix = 'orders';

    protected static ?string $model = Order::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationLabel = 'Zlecenia';

    protected static ?string $modelLabel = 'Zlecenie';

    protected static ?string $pluralModelLabel = 'Zlecenia';

    protected static ?string $navigationGroup = 'Sprzedaż';

    protected static ?int $navigationSort = 1;

    // Widoczność w nawigacji jest zarządzana przez HasNavigationPermission trait
    // na podstawie uprawnienia 'orders.view_any'

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Informacje podstawowe')
                    ->schema([
                        Forms\Components\TextInput::make('order_number')
                            ->label('Numer zlecenia')
                            ->default(fn () => Order::generateOrderNumber())
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(20),

                        Forms\Components\TextInput::make('title')
                            ->label('Tytuł')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),

                        Forms\Components\Select::make('customer_id')
                            ->label('Klient')
                            ->relationship('customer', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->createOptionForm([
                                Forms\Components\TextInput::make('name')
                                    ->label('Nazwa')
                                    ->required(),
                                Forms\Components\TextInput::make('email')
                                    ->label('Email')
                                    ->email(),
                            ]),

                        Forms\Components\Select::make('site_id')
                            ->label('Strona')
                            ->relationship('site', 'name')
                            ->searchable()
                            ->preload(),

                        Forms\Components\Select::make('type')
                            ->label('Typ')
                            ->options([
                                'initial' => 'Nowa strona',
                                'correction' => 'Poprawka',
                                'development' => 'Rozwój',
                                'maintenance' => 'Utrzymanie',
                            ])
                            ->default('initial')
                            ->required(),

                        Forms\Components\Select::make('status')
                            ->label('Status')
                            ->options([
                                'draft' => 'Szkic',
                                'offer_sent' => 'Oferta wysłana',
                                'accepted' => 'Zaakceptowane',
                                'in_progress' => 'W realizacji',
                                'delivered' => 'Dostarczone',
                                'paid' => 'Opłacone',
                                'completed' => 'Zakończone',
                                'cancelled' => 'Anulowane',
                                'dispute_useme' => 'Spór Useme',
                                'dispute_resolved' => 'Spór rozwiązany',
                            ])
                            ->default('draft')
                            ->required(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Zakres i wymagania')
                    ->schema([
                        Forms\Components\RichEditor::make('scope')
                            ->label('Zakres prac')
                            ->columnSpanFull(),

                        Forms\Components\RichEditor::make('requirements')
                            ->label('Wymagania klienta')
                            ->columnSpanFull(),
                    ])
                    ->collapsible(),

                Forms\Components\Section::make('Finanse')
                    ->schema([
                        Forms\Components\TextInput::make('price')
                            ->label('Cena')
                            ->numeric()
                            ->prefix('PLN')
                            ->required(),

                        Forms\Components\Select::make('currency')
                            ->label('Waluta')
                            ->options([
                                'PLN' => 'PLN',
                                'EUR' => 'EUR',
                                'USD' => 'USD',
                            ])
                            ->default('PLN'),

                        Forms\Components\TextInput::make('estimated_days')
                            ->label('Szacowany czas (dni)')
                            ->numeric()
                            ->minValue(1),

                        Forms\Components\DatePicker::make('deadline_at')
                            ->label('Deadline'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Terminy')
                    ->schema([
                        Forms\Components\DateTimePicker::make('offer_sent_at')
                            ->label('Oferta wysłana'),

                        Forms\Components\DateTimePicker::make('accepted_at')
                            ->label('Zaakceptowane'),

                        Forms\Components\DateTimePicker::make('started_at')
                            ->label('Rozpoczęte'),

                        Forms\Components\DateTimePicker::make('delivered_at')
                            ->label('Dostarczone'),

                        Forms\Components\DateTimePicker::make('paid_at')
                            ->label('Opłacone'),

                        Forms\Components\DateTimePicker::make('completed_at')
                            ->label('Zakończone'),

                        Forms\Components\DateTimePicker::make('free_corrections_until')
                            ->label('Darmowe poprawki do'),
                    ])
                    ->columns(3)
                    ->collapsible()
                    ->collapsed(),

                Forms\Components\Section::make('Useme')
                    ->schema([
                        Forms\Components\TextInput::make('useme_offer_url')
                            ->label('URL oferty Useme')
                            ->url()
                            ->maxLength(500),

                        Forms\Components\Select::make('listing_id')
                            ->label('Ogłoszenie źródłowe')
                            ->relationship('listing', 'title')
                            ->searchable()
                            ->preload(),
                    ])
                    ->columns(2)
                    ->collapsible()
                    ->collapsed(),

                Forms\Components\Section::make('Przypisanie')
                    ->schema([
                        Forms\Components\Select::make('assigned_to')
                            ->label('Przypisane do')
                            ->relationship('assignedTo', 'name')
                            ->searchable()
                            ->preload(),

                        Forms\Components\Textarea::make('internal_notes')
                            ->label('Notatki wewnętrzne')
                            ->rows(3)
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
                Tables\Columns\TextColumn::make('order_number')
                    ->label('Numer')
                    ->searchable()
                    ->sortable()
                    ->copyable(),

                Tables\Columns\TextColumn::make('title')
                    ->label('Tytuł')
                    ->searchable()
                    ->limit(40)
                    ->tooltip(fn ($record) => $record->title),

                Tables\Columns\TextColumn::make('customer.name')
                    ->label('Klient')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\BadgeColumn::make('type')
                    ->label('Typ')
                    ->colors([
                        'primary' => 'initial',
                        'warning' => 'correction',
                        'info' => 'development',
                        'gray' => 'maintenance',
                    ])
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'initial' => 'Nowa',
                        'correction' => 'Poprawka',
                        'development' => 'Rozwój',
                        'maintenance' => 'Utrzymanie',
                        default => $state,
                    }),

                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->colors([
                        'gray' => 'draft',
                        'info' => 'offer_sent',
                        'warning' => fn ($state) => in_array($state, ['accepted', 'in_progress']),
                        'primary' => 'delivered',
                        'success' => fn ($state) => in_array($state, ['paid', 'completed']),
                        'danger' => fn ($state) => str_starts_with($state, 'dispute') || $state === 'cancelled',
                    ])
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'draft' => 'Szkic',
                        'offer_sent' => 'Oferta',
                        'accepted' => 'Przyjęte',
                        'in_progress' => 'W toku',
                        'delivered' => 'Dostarczone',
                        'paid' => 'Opłacone',
                        'completed' => 'Zakończone',
                        'cancelled' => 'Anulowane',
                        'dispute_useme' => 'Spór',
                        default => $state,
                    }),

                Tables\Columns\TextColumn::make('price')
                    ->label('Cena')
                    ->money('PLN')
                    ->sortable(),

                Tables\Columns\TextColumn::make('deadline_at')
                    ->label('Deadline')
                    ->date('d.m.Y')
                    ->sortable()
                    ->color(fn ($record) => $record->deadline_at?->isPast() ? 'danger' : null),

                Tables\Columns\TextColumn::make('assignedTo.name')
                    ->label('Przypisane')
                    ->toggleable(isToggledHiddenByDefault: true),

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
                        'draft' => 'Szkic',
                        'offer_sent' => 'Oferta wysłana',
                        'accepted' => 'Zaakceptowane',
                        'in_progress' => 'W realizacji',
                        'delivered' => 'Dostarczone',
                        'paid' => 'Opłacone',
                        'completed' => 'Zakończone',
                        'cancelled' => 'Anulowane',
                    ]),

                Tables\Filters\SelectFilter::make('type')
                    ->label('Typ')
                    ->options([
                        'initial' => 'Nowa strona',
                        'correction' => 'Poprawka',
                        'development' => 'Rozwój',
                        'maintenance' => 'Utrzymanie',
                    ]),

                Tables\Filters\SelectFilter::make('customer_id')
                    ->label('Klient')
                    ->relationship('customer', 'name')
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('assigned_to')
                    ->label('Przypisane do')
                    ->relationship('assignedTo', 'name'),

                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\Action::make('mark_paid')
                    ->label('Oznacz opłacone')
                    ->icon('heroicon-o-banknotes')
                    ->color('success')
                    ->visible(fn ($record) => $record->status === 'delivered')
                    ->requiresConfirmation()
                    ->action(fn ($record) => $record->update([
                        'status' => 'paid',
                        'paid_at' => now(),
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
            ->defaultSort('created_at', 'desc');
        
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
            'index' => Pages\ListOrders::route('/'),
            'create' => Pages\CreateOrder::route('/create'),
            'view' => Pages\ViewOrder::route('/{record}'),
            'edit' => Pages\EditOrder::route('/{record}/edit'),
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
        return (string) static::getModel()::whereIn('status', ['in_progress', 'delivered'])->count() ?: null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }
}

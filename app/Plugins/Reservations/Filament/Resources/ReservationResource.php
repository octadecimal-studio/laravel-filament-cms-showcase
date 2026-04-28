<?php

declare(strict_types=1);

namespace App\Plugins\Reservations\Filament\Resources;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\DateTimePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use App\Plugins\Reservations\Filament\Resources\ReservationResource\Pages\ListReservations;
use App\Plugins\Reservations\Filament\Resources\ReservationResource\Pages\CreateReservation;
use App\Plugins\Reservations\Filament\Resources\ReservationResource\Pages\EditReservation;
use App\Plugins\Reservations\Filament\Resources\ReservationResource\Pages\ViewReservation;
use App\Modules\Content\Models\TwoWheels\Motorcycle;
use App\Modules\Core\Scopes\TenantScope;
use App\Plugins\Reservations\Filament\Resources\ReservationResource\Pages;
use App\Plugins\Reservations\Models\Reservation;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Filament Resource dla rezerwacji.
 *
 * Panel zarządzania rezerwacjami z możliwością:
 * - Przeglądania listy rezerwacji
 * - Zmiany statusu
 * - Filtrowania i wyszukiwania
 * - Eksportu
 */
class ReservationResource extends Resource
{

    /**
     * Model.
     */
    protected static ?string $model = Reservation::class;

    /**
     * Ikona w nawigacji.
     */
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-calendar-days';

    /**
     * Etykieta nawigacji.
     */
    protected static ?string $navigationLabel = 'Rezerwacje';

    /**
     * Etykieta modelu (liczba pojedyncza).
     */
    protected static ?string $modelLabel = 'Rezerwacja';

    /**
     * Etykieta modelu (liczba mnoga).
     */
    protected static ?string $pluralModelLabel = 'Rezerwacje';

    /**
     * Grupa nawigacji.
     */
    protected static ?int $navigationSort = 85;

    /**
     * Ukrycie z sidebara — stary system rezerwacji zastapiony przez
     * pakiet octadecimalhq/reservation-system (RentalResource /admin/rentals).
     * Kod pozostaje w repo do czasu pelnej migracji historycznych danych.
     *
     * @see KML-0067 fix-2
     */
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    /**
     * Definicja formularza.
     */
    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Dane klienta')
                    ->schema([
                        TextInput::make('customer_name')
                            ->label('Imię i nazwisko')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('customer_email')
                            ->label('Email')
                            ->email()
                            ->required()
                            ->maxLength(255),

                        TextInput::make('customer_phone')
                            ->label('Telefon')
                            ->tel()
                            ->required()
                            ->maxLength(20),
                    ])
                    ->columns(3),

                Section::make('Szczegóły rezerwacji')
                    ->schema([
                        Select::make('motorcycle_id')
                            ->label('Motocykl')
                            ->options(function (): array {
                                $q = Motorcycle::query()
                                    ->withoutGlobalScope(TenantScope::class)
                                    ->orderBy('name');
                                $user = auth()->user();
                                if ($user && !($user->is_super_admin || $user->hasRole('super_admin'))) {
                                    $q->where('tenant_id', $user->tenant_id);
                                }
                                return $q->pluck('name', 'id')->all();
                            })
                            ->searchable()
                            ->nullable(),

                        DatePicker::make('pickup_date')
                            ->label('Data odbioru')
                            ->required()
                            ->native(false),

                        DatePicker::make('return_date')
                            ->label('Data zwrotu')
                            ->required()
                            ->native(false)
                            ->afterOrEqual('pickup_date'),

                        Select::make('status')
                            ->label('Status')
                            ->options(Reservation::statuses())
                            ->required()
                            ->default('pending'),

                        TextInput::make('total_price')
                            ->label('Cena całkowita')
                            ->numeric()
                            ->prefix('PLN')
                            ->nullable(),
                    ])
                    ->columns(2),

                Section::make('Dodatkowe informacje')
                    ->schema([
                        Textarea::make('notes')
                            ->label('Notatki')
                            ->rows(3)
                            ->columnSpanFull(),

                        Toggle::make('rodo_consent')
                            ->label('Zgoda RODO')
                            ->disabled()
                            ->dehydrated(false),

                        DateTimePicker::make('rodo_consent_at')
                            ->label('Data zgody RODO')
                            ->disabled()
                            ->dehydrated(false),
                    ]),
            ]);
    }

    /**
     * Definicja tabeli.
     */
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('pickup_date')
                    ->label('Odbiór')
                    ->date('d.m.Y')
                    ->sortable(),

                TextColumn::make('return_date')
                    ->label('Zwrot')
                    ->date('d.m.Y')
                    ->sortable(),

                TextColumn::make('customer_name')
                    ->label('Klient')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('customer_phone')
                    ->label('Telefon')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('motorcycle.name')
                    ->label('Motocykl')
                    ->formatStateUsing(fn (?string $state, Reservation $record): string => $record->motorcycle?->name ?? 'Brak')
                    ->placeholder('Brak')
                    ->toggleable(),

                BadgeColumn::make('status')
                    ->label('Status')
                    ->colors([
                        'warning' => 'pending',
                        'success' => 'confirmed',
                        'danger' => 'cancelled',
                        'info' => 'completed',
                    ])
                    ->formatStateUsing(fn (string $state): string => Reservation::statuses()[$state] ?? $state),

                TextColumn::make('total_price')
                    ->label('Cena')
                    ->money('PLN')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('created_at')
                    ->label('Utworzono')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(Reservation::statuses()),

                Filter::make('pickup_date')
                    ->schema([
                        DatePicker::make('from')
                            ->label('Odbiór od'),
                        DatePicker::make('until')
                            ->label('Odbiór do'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('pickup_date', '>=', $date),
                            )
                            ->when(
                                $data['until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('pickup_date', '<=', $date),
                            );
                    }),

                TrashedFilter::make(),
            ])
            ->recordActions([
                Action::make('confirm')
                    ->label('Potwierdź')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->visible(fn (Reservation $record): bool => $record->isPending())
                    ->requiresConfirmation()
                    ->action(fn (Reservation $record) => $record->confirm()),

                Action::make('cancel')
                    ->label('Anuluj')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->visible(fn (Reservation $record): bool => $record->isPending() || $record->isConfirmed())
                    ->requiresConfirmation()
                    ->action(fn (Reservation $record) => $record->cancel()),

                EditAction::make(),
                ViewAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ])
            ->defaultSort('pickup_date', 'asc')
            ->poll('30s'); // Odświeżanie co 30 sekund
    }

    /**
     * Relacje do ładowania.
     */
    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    /**
     * Strony zasobu.
     */
    public static function getPages(): array
    {
        return [
            'index' => ListReservations::route('/'),
            'create' => CreateReservation::route('/create'),
            'edit' => EditReservation::route('/{record}/edit'),
            'view' => ViewReservation::route('/{record}'),
        ];
    }

    /**
     * Filtruj dane po tenant_id dla klientów.
     */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->with('motorcycle')
            ->withoutGlobalScopes([
                TenantScope::class,
            ]);

        $user = auth()->user();
        // Klient widzi tylko rezerwacje z własnego tenanta
        if ($user && !($user->is_super_admin || $user->hasRole('super_admin'))) {
            $query->where('tenant_id', $user->tenant_id);
        }

        return $query;
    }

    /**
     * Badge z liczbą oczekujących rezerwacji.
     */
    public static function getNavigationBadge(): ?string
    {
        $user = auth()->user();
        
        $query = static::getModel()::withoutGlobalScope(TenantScope::class)
            ->pending();
        
        // Klient widzi tylko swoje rezerwacje
        if ($user && !($user->is_super_admin || $user->hasRole('super_admin'))) {
            $query->where('tenant_id', $user->tenant_id);
        }
        
        $count = $query->count();
        return $count > 0 ? (string) $count : null;
    }

    /**
     * Kolor badge.
     */
    public static function getNavigationBadgeColor(): ?string
    {
        $count = static::getModel()::pending()->count();
        return $count > 0 ? 'warning' : null;
    }
}

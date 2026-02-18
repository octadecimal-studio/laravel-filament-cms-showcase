<?php

declare(strict_types=1);

namespace App\Plugins\Reservations\Filament\Resources;

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
    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';

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
     * Definicja formularza.
     */
    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Dane klienta')
                    ->schema([
                        Forms\Components\TextInput::make('customer_name')
                            ->label('Imię i nazwisko')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('customer_email')
                            ->label('Email')
                            ->email()
                            ->required()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('customer_phone')
                            ->label('Telefon')
                            ->tel()
                            ->required()
                            ->maxLength(20),
                    ])
                    ->columns(3),

                Forms\Components\Section::make('Szczegóły rezerwacji')
                    ->schema([
                        Forms\Components\Select::make('motorcycle_id')
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

                        Forms\Components\DatePicker::make('pickup_date')
                            ->label('Data odbioru')
                            ->required()
                            ->native(false),

                        Forms\Components\DatePicker::make('return_date')
                            ->label('Data zwrotu')
                            ->required()
                            ->native(false)
                            ->afterOrEqual('pickup_date'),

                        Forms\Components\Select::make('status')
                            ->label('Status')
                            ->options(Reservation::statuses())
                            ->required()
                            ->default('pending'),

                        Forms\Components\TextInput::make('total_price')
                            ->label('Cena całkowita')
                            ->numeric()
                            ->prefix('PLN')
                            ->nullable(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Dodatkowe informacje')
                    ->schema([
                        Forms\Components\Textarea::make('notes')
                            ->label('Notatki')
                            ->rows(3)
                            ->columnSpanFull(),

                        Forms\Components\Toggle::make('rodo_consent')
                            ->label('Zgoda RODO')
                            ->disabled()
                            ->dehydrated(false),

                        Forms\Components\DateTimePicker::make('rodo_consent_at')
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
                Tables\Columns\TextColumn::make('pickup_date')
                    ->label('Odbiór')
                    ->date('d.m.Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('return_date')
                    ->label('Zwrot')
                    ->date('d.m.Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('customer_name')
                    ->label('Klient')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('customer_phone')
                    ->label('Telefon')
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('motorcycle.name')
                    ->label('Motocykl')
                    ->formatStateUsing(fn (?string $state, Reservation $record): string => $record->motorcycle?->name ?? 'Brak')
                    ->placeholder('Brak')
                    ->toggleable(),

                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->colors([
                        'warning' => 'pending',
                        'success' => 'confirmed',
                        'danger' => 'cancelled',
                        'info' => 'completed',
                    ])
                    ->formatStateUsing(fn (string $state): string => Reservation::statuses()[$state] ?? $state),

                Tables\Columns\TextColumn::make('total_price')
                    ->label('Cena')
                    ->money('PLN')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Utworzono')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options(Reservation::statuses()),

                Tables\Filters\Filter::make('pickup_date')
                    ->form([
                        Forms\Components\DatePicker::make('from')
                            ->label('Odbiór od'),
                        Forms\Components\DatePicker::make('until')
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

                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\Action::make('confirm')
                    ->label('Potwierdź')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->visible(fn (Reservation $record): bool => $record->isPending())
                    ->requiresConfirmation()
                    ->action(fn (Reservation $record) => $record->confirm()),

                Tables\Actions\Action::make('cancel')
                    ->label('Anuluj')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->visible(fn (Reservation $record): bool => $record->isPending() || $record->isConfirmed())
                    ->requiresConfirmation()
                    ->action(fn (Reservation $record) => $record->cancel()),

                Tables\Actions\EditAction::make(),
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\ForceDeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
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
            'index' => Pages\ListReservations::route('/'),
            'create' => Pages\CreateReservation::route('/create'),
            'edit' => Pages\EditReservation::route('/{record}/edit'),
            'view' => Pages\ViewReservation::route('/{record}'),
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
                \App\Modules\Core\Scopes\TenantScope::class,
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
        
        $query = static::getModel()::withoutGlobalScope(\App\Modules\Core\Scopes\TenantScope::class)
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

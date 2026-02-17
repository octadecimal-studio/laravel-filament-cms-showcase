<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Concerns\HasGlobalBulkActions;
use App\Filament\Concerns\HasNavigationPermission;
use App\Filament\Concerns\RemembersTableSettings;
use App\Filament\Resources\InvoiceResource\Pages;
use App\Models\Invoice;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

/**
 * Filament Resource dla faktur.
 */
class InvoiceResource extends Resource
{
    use HasGlobalBulkActions;
    use HasNavigationPermission;
    use RemembersTableSettings;

    /**
     * Prefix uprawnień dla tego zasobu.
     */
    protected static string $permissionPrefix = 'invoices';

    protected static ?string $model = Invoice::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationLabel = 'Faktury';

    protected static ?string $modelLabel = 'Faktura';

    protected static ?string $pluralModelLabel = 'Faktury';

    protected static ?string $navigationGroup = 'Finanse';

    protected static ?int $navigationSort = 1;

    // Widoczność w nawigacji jest zarządzana przez HasNavigationPermission trait
    // na podstawie uprawnienia 'invoices.view_any'

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Informacje podstawowe')
                    ->schema([
                        Forms\Components\TextInput::make('invoice_number')
                            ->label('Numer faktury')
                            ->default(fn () => Invoice::generateInvoiceNumber())
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(30),

                        Forms\Components\Select::make('type')
                            ->label('Typ')
                            ->options([
                                'invoice' => 'Faktura VAT',
                                'proforma' => 'Proforma',
                                'correction' => 'Korekta',
                            ])
                            ->default('invoice')
                            ->required(),

                        Forms\Components\Select::make('customer_id')
                            ->label('Klient')
                            ->relationship('customer', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),

                        Forms\Components\Select::make('order_id')
                            ->label('Zlecenie')
                            ->relationship('order', 'order_number')
                            ->searchable()
                            ->preload(),

                        Forms\Components\Select::make('status')
                            ->label('Status')
                            ->options([
                                'draft' => 'Szkic',
                                'sent' => 'Wysłana',
                                'paid' => 'Opłacona',
                                'overdue' => 'Po terminie',
                                'cancelled' => 'Anulowana',
                            ])
                            ->default('draft')
                            ->required(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Kwoty')
                    ->schema([
                        Forms\Components\TextInput::make('subtotal')
                            ->label('Kwota netto')
                            ->numeric()
                            ->prefix('PLN')
                            ->required(),

                        Forms\Components\TextInput::make('discount_amount')
                            ->label('Rabat')
                            ->numeric()
                            ->prefix('PLN')
                            ->default(0),

                        Forms\Components\TextInput::make('tax_amount')
                            ->label('VAT')
                            ->numeric()
                            ->prefix('PLN')
                            ->default(0),

                        Forms\Components\TextInput::make('total')
                            ->label('Kwota brutto')
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
                    ])
                    ->columns(3),

                Forms\Components\Section::make('Daty')
                    ->schema([
                        Forms\Components\DatePicker::make('issue_date')
                            ->label('Data wystawienia')
                            ->default(now())
                            ->required(),

                        Forms\Components\DatePicker::make('due_date')
                            ->label('Termin płatności')
                            ->default(now()->addDays(14))
                            ->required(),

                        Forms\Components\DateTimePicker::make('paid_at')
                            ->label('Data zapłaty'),
                    ])
                    ->columns(3),

                Forms\Components\Section::make('Dane nabywcy')
                    ->schema([
                        Forms\Components\TextInput::make('buyer_name')
                            ->label('Nazwa nabywcy')
                            ->maxLength(255),

                        Forms\Components\TextInput::make('buyer_nip')
                            ->label('NIP nabywcy')
                            ->maxLength(15),

                        Forms\Components\Textarea::make('buyer_address')
                            ->label('Adres nabywcy')
                            ->rows(3),
                    ])
                    ->columns(2)
                    ->collapsible(),

                Forms\Components\Section::make('Dodatkowe')
                    ->schema([
                        Forms\Components\TextInput::make('pdf_url')
                            ->label('URL do PDF')
                            ->url()
                            ->maxLength(500),

                        Forms\Components\Textarea::make('notes')
                            ->label('Notatki')
                            ->rows(3),
                    ])
                    ->collapsible()
                    ->collapsed(),
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
                Tables\Columns\TextColumn::make('invoice_number')
                    ->label('Numer')
                    ->searchable()
                    ->sortable()
                    ->copyable(),

                Tables\Columns\BadgeColumn::make('type')
                    ->label('Typ')
                    ->colors([
                        'primary' => 'invoice',
                        'warning' => 'proforma',
                        'danger' => 'correction',
                    ])
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'invoice' => 'Faktura',
                        'proforma' => 'Proforma',
                        'correction' => 'Korekta',
                        default => $state,
                    }),

                Tables\Columns\TextColumn::make('customer.name')
                    ->label('Klient')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('total')
                    ->label('Kwota')
                    ->money('PLN')
                    ->sortable(),

                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->colors([
                        'gray' => 'draft',
                        'info' => 'sent',
                        'success' => 'paid',
                        'danger' => fn ($state) => in_array($state, ['overdue', 'cancelled']),
                    ])
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'draft' => 'Szkic',
                        'sent' => 'Wysłana',
                        'paid' => 'Opłacona',
                        'overdue' => 'Po terminie',
                        'cancelled' => 'Anulowana',
                        default => $state,
                    }),

                Tables\Columns\TextColumn::make('issue_date')
                    ->label('Wystawiona')
                    ->date('d.m.Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('due_date')
                    ->label('Termin')
                    ->date('d.m.Y')
                    ->sortable()
                    ->color(fn ($record) => $record->status === 'sent' && $record->due_date?->isPast() ? 'danger' : null),

                Tables\Columns\TextColumn::make('paid_at')
                    ->label('Zapłacona')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('order.order_number')
                    ->label('Zlecenie')
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
                        'sent' => 'Wysłana',
                        'paid' => 'Opłacona',
                        'overdue' => 'Po terminie',
                        'cancelled' => 'Anulowana',
                    ]),

                Tables\Filters\SelectFilter::make('type')
                    ->label('Typ')
                    ->options([
                        'invoice' => 'Faktura VAT',
                        'proforma' => 'Proforma',
                        'correction' => 'Korekta',
                    ]),

                Tables\Filters\SelectFilter::make('customer_id')
                    ->label('Klient')
                    ->relationship('customer', 'name')
                    ->searchable()
                    ->preload(),

                Tables\Filters\Filter::make('overdue')
                    ->label('Po terminie')
                    ->query(fn (Builder $query): Builder => $query
                        ->where('status', 'sent')
                        ->where('due_date', '<', now())),

                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\Action::make('download_pdf')
                    ->label('PDF')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->url(fn ($record) => $record->pdf_url)
                    ->openUrlInNewTab()
                    ->visible(fn ($record) => !empty($record->pdf_url)),

                Tables\Actions\Action::make('mark_paid')
                    ->label('Oznacz opłaconą')
                    ->icon('heroicon-o-banknotes')
                    ->color('success')
                    ->visible(fn ($record) => in_array($record->status, ['sent', 'overdue']))
                    ->requiresConfirmation()
                    ->action(fn ($record) => $record->update([
                        'status' => 'paid',
                        'paid_at' => now(),
                    ])),

                Tables\Actions\Action::make('send')
                    ->label('Wyślij')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('primary')
                    ->visible(fn ($record) => $record->status === 'draft')
                    ->requiresConfirmation()
                    ->action(fn ($record) => $record->update(['status' => 'sent'])),

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
            ->defaultSort('issue_date', 'desc');
        
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
            'index' => Pages\ListInvoices::route('/'),
            'create' => Pages\CreateInvoice::route('/create'),
            'view' => Pages\ViewInvoice::route('/{record}'),
            'edit' => Pages\EditInvoice::route('/{record}/edit'),
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
        return (string) static::getModel()::where('status', 'sent')
            ->where('due_date', '<', now())
            ->count() ?: null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }
}

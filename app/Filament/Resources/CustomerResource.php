<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Concerns\HasGlobalBulkActions;
use App\Filament\Concerns\HasNavigationPermission;
use App\Filament\Concerns\RemembersTableSettings;
use App\Filament\Resources\CustomerResource\Pages;
use App\Models\Customer;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

/**
 * Filament Resource dla klientów.
 */
class CustomerResource extends Resource
{
    use HasGlobalBulkActions;
    use HasNavigationPermission;
    use RemembersTableSettings;

    /**
     * Prefix uprawnień dla tego zasobu.
     */
    protected static string $permissionPrefix = 'customers';

    protected static ?string $model = Customer::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationLabel = 'Klienci';

    protected static ?string $modelLabel = 'Klient';

    protected static ?string $pluralModelLabel = 'Klienci';

    protected static ?string $navigationGroup = 'CRM';

    protected static ?int $navigationSort = 1;

    // Widoczność w nawigacji jest zarządzana przez HasNavigationPermission trait
    // na podstawie uprawnienia 'customers.view_any'

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Dane podstawowe')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nazwa / Imię i nazwisko')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('slug')
                            ->label('Slug')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),

                        Forms\Components\TextInput::make('code')
                            ->label('Kod klienta')
                            ->maxLength(20)
                            ->placeholder('np. KL001'),

                        Forms\Components\Select::make('status')
                            ->label('Status')
                            ->options([
                                'lead' => 'Lead',
                                'active' => 'Aktywny',
                                'inactive' => 'Nieaktywny',
                                'churned' => 'Churned',
                            ])
                            ->default('lead')
                            ->required(),

                        Forms\Components\Toggle::make('is_vip')
                            ->label('VIP')
                            ->default(false),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Dane firmy')
                    ->schema([
                        Forms\Components\TextInput::make('company_name')
                            ->label('Nazwa firmy')
                            ->maxLength(255),

                        Forms\Components\TextInput::make('nip')
                            ->label('NIP')
                            ->maxLength(15),

                        Forms\Components\TextInput::make('regon')
                            ->label('REGON')
                            ->maxLength(14),

                        Forms\Components\TextInput::make('website')
                            ->label('Strona WWW')
                            ->url()
                            ->maxLength(255),
                    ])
                    ->columns(2)
                    ->collapsible(),

                Forms\Components\Section::make('Kontakt')
                    ->schema([
                        Forms\Components\TextInput::make('email')
                            ->label('Email')
                            ->email()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('phone')
                            ->label('Telefon')
                            ->tel()
                            ->maxLength(20),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Adres')
                    ->schema([
                        Forms\Components\TextInput::make('address_street')
                            ->label('Ulica i numer')
                            ->maxLength(255),

                        Forms\Components\TextInput::make('address_city')
                            ->label('Miasto')
                            ->maxLength(100),

                        Forms\Components\TextInput::make('address_postal')
                            ->label('Kod pocztowy')
                            ->maxLength(10),

                        Forms\Components\TextInput::make('address_country')
                            ->label('Kraj')
                            ->default('Polska')
                            ->maxLength(100),
                    ])
                    ->columns(2)
                    ->collapsible(),

                Forms\Components\Section::make('Źródło pozyskania')
                    ->schema([
                        Forms\Components\Select::make('source')
                            ->label('Źródło')
                            ->options([
                                'useme' => 'Useme.com',
                                'referral' => 'Polecenie',
                                'google' => 'Google',
                                'social' => 'Social Media',
                                'direct' => 'Bezpośredni',
                                'other' => 'Inne',
                            ]),

                        Forms\Components\TextInput::make('source_url')
                            ->label('URL źródła')
                            ->url()
                            ->maxLength(500),

                        Forms\Components\TextInput::make('referral_code')
                            ->label('Kod polecający')
                            ->maxLength(50),
                    ])
                    ->columns(3)
                    ->collapsible(),

                Forms\Components\Section::make('Notatki')
                    ->schema([
                        Forms\Components\Textarea::make('notes')
                            ->label('Notatki (widoczne dla klienta)')
                            ->rows(3),

                        Forms\Components\Textarea::make('internal_notes')
                            ->label('Notatki wewnętrzne')
                            ->rows(3),
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
                Tables\Columns\TextColumn::make('name')
                    ->label('Nazwa')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('company_name')
                    ->label('Firma')
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->copyable(),

                Tables\Columns\TextColumn::make('phone')
                    ->label('Telefon')
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->colors([
                        'warning' => 'lead',
                        'success' => 'active',
                        'gray' => 'inactive',
                        'danger' => 'churned',
                    ]),

                Tables\Columns\IconColumn::make('is_vip')
                    ->label('VIP')
                    ->boolean()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('sites_count')
                    ->label('Strony')
                    ->counts('sites')
                    ->sortable(),

                Tables\Columns\TextColumn::make('orders_count')
                    ->label('Zlecenia')
                    ->counts('orders')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('source')
                    ->label('Źródło')
                    ->badge()
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
                    ->options([
                        'lead' => 'Lead',
                        'active' => 'Aktywny',
                        'inactive' => 'Nieaktywny',
                        'churned' => 'Churned',
                    ]),

                Tables\Filters\TernaryFilter::make('is_vip')
                    ->label('VIP'),

                Tables\Filters\SelectFilter::make('source')
                    ->label('Źródło')
                    ->options([
                        'useme' => 'Useme.com',
                        'referral' => 'Polecenie',
                        'google' => 'Google',
                        'social' => 'Social Media',
                        'direct' => 'Bezpośredni',
                    ]),

                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
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
            'index' => Pages\ListCustomers::route('/'),
            'create' => Pages\CreateCustomer::route('/create'),
            'view' => Pages\ViewCustomer::route('/{record}'),
            'edit' => Pages\EditCustomer::route('/{record}/edit'),
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
        return (string) static::getModel()::where('status', 'lead')->count() ?: null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }
}

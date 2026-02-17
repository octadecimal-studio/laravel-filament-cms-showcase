<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Concerns\HasNavigationPermission;
use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use App\Modules\Core\Models\Tenant;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

/**
 * Filament Resource dla użytkowników systemu.
 */
class UserResource extends Resource
{
    use HasNavigationPermission;

    /**
     * Prefix uprawnień dla tego zasobu.
     */
    protected static string $permissionPrefix = 'users';
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationLabel = 'Użytkownicy';

    protected static ?string $modelLabel = 'Użytkownik';

    protected static ?string $pluralModelLabel = 'Użytkownicy';

    protected static ?string $navigationGroup = 'System';

    protected static ?int $navigationSort = 10;

    // Widoczność w nawigacji jest zarządzana przez HasNavigationPermission trait
    // na podstawie uprawnienia 'users.view_any'
    // Super admini mają automatycznie dostęp do wszystkiego

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Dane podstawowe')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Imię i nazwisko')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('email')
                            ->label('Email')
                            ->email()
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),

                        Forms\Components\TextInput::make('password')
                            ->label('Hasło')
                            ->password()
                            ->required(fn (string $operation): bool => $operation === 'create')
                            ->dehydrated(fn ($state) => filled($state))
                            ->dehydrateStateUsing(fn ($state) => Hash::make($state))
                            ->minLength(8)
                            ->helperText('Minimum 8 znaków. Pozostaw puste przy edycji, aby nie zmieniać hasła.'),

                        Forms\Components\Toggle::make('email_verified_at')
                            ->label('Email zweryfikowany')
                            ->default(true)
                            ->dehydrated(false)
                            ->afterStateUpdated(function ($state, Forms\Set $set) {
                                $set('email_verified_at', $state ? now() : null);
                            }),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Uprawnienia')
                    ->schema([
                        Forms\Components\Select::make('role')
                            ->label('Rola')
                            ->options(function () {
                                return Role::where('guard_name', 'web')
                                    ->pluck('name', 'name')
                                    ->toArray();
                            })
                            ->required()
                            ->default('tenant_admin')
                            ->helperText('Wybierz rolę użytkownika. Super Admin ma dostęp do wszystkich tenantów.'),

                        Forms\Components\Toggle::make('is_super_admin')
                            ->label('Super Administrator')
                            ->default(false)
                            ->helperText('Super admin ma dostęp do wszystkich tenantów i pełne uprawnienia.')
                            ->visible(fn ($get) => $get('role') === 'super_admin')
                            ->afterStateUpdated(function ($state, Forms\Set $set) {
                                if ($state) {
                                    // Przypisz do system tenant (Tenant 0)
                                    $systemTenant = \App\Modules\Core\Models\Tenant::getSystemTenant();
                                    if ($systemTenant) {
                                        $set('tenant_id', $systemTenant->id);
                                    }
                                }
                            }),

                        Forms\Components\Select::make('tenant_id')
                            ->label('Tenant')
                            ->relationship('tenant', 'name', fn ($query) => $query->where('slug', '!=', 'system'))
                            ->searchable()
                            ->preload()
                            ->required(fn (Forms\Get $get) => $get('role') !== 'super_admin')
                            ->visible(fn (Forms\Get $get) => $get('role') !== 'super_admin')
                            ->helperText('Wybierz tenant dla użytkownika. Super Admin jest automatycznie przypisany do Tenant System.'),

                        Forms\Components\Toggle::make('send_invitation')
                            ->label('Wyślij email z zaproszeniem')
                            ->default(true)
                            ->helperText('Wyślij email z linkiem do ustawienia hasła (jeśli włączone).')
                            ->visible(fn (string $operation): bool => $operation === 'create'),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Imię i nazwisko')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->sortable()
                    ->copyable(),

                Tables\Columns\TextColumn::make('roles.name')
                    ->label('Rola')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'super_admin' => 'danger',
                        'tenant_admin' => 'warning',
                        'client' => 'info',
                        'editor' => 'success',
                        default => 'gray',
                    })
                    ->searchable(),

                Tables\Columns\IconColumn::make('is_super_admin')
                    ->label('Super Admin')
                    ->boolean()
                    ->sortable(),

                Tables\Columns\TextColumn::make('tenant.name')
                    ->label('Tenant')
                    ->searchable()
                    ->sortable()
                    ->default('—'),

                Tables\Columns\IconColumn::make('email_verified_at')
                    ->label('Email zweryfikowany')
                    ->boolean()
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Utworzono')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('role')
                    ->label('Rola')
                    ->relationship('roles', 'name')
                    ->multiple(),

                Tables\Filters\TernaryFilter::make('is_super_admin')
                    ->label('Super Admin')
                    ->placeholder('Wszyscy')
                    ->trueLabel('Tylko Super Admini')
                    ->falseLabel('Bez Super Adminów'),

                Tables\Filters\SelectFilter::make('tenant_id')
                    ->label('Tenant')
                    ->relationship('tenant', 'name')
                    ->searchable()
                    ->preload(),

                Tables\Filters\TernaryFilter::make('email_verified_at')
                    ->label('Email zweryfikowany')
                    ->placeholder('Wszyscy')
                    ->trueLabel('Zweryfikowani')
                    ->falseLabel('Niezwerfikowani'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
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
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['roles', 'tenant']);
    }
}

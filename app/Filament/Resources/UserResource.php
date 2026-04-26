<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use App\Filament\Resources\UserResource\Pages\ListUsers;
use App\Filament\Resources\UserResource\Pages\CreateUser;
use App\Filament\Resources\UserResource\Pages\EditUser;
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
    protected static ?string $model = User::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationLabel = 'Użytkownicy';

    protected static ?string $modelLabel = 'Użytkownik';

    protected static ?string $pluralModelLabel = 'Użytkownicy';

    protected static ?int $navigationSort = 90;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Dane podstawowe')
                    ->schema([
                        TextInput::make('name')
                            ->label('Imię i nazwisko')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('email')
                            ->label('Email')
                            ->email()
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),

                        TextInput::make('password')
                            ->label('Hasło')
                            ->password()
                            ->required(fn (string $operation): bool => $operation === 'create')
                            ->dehydrated(fn ($state) => filled($state))
                            ->dehydrateStateUsing(fn ($state) => Hash::make($state))
                            ->minLength(8)
                            ->helperText('Minimum 8 znaków. Pozostaw puste przy edycji, aby nie zmieniać hasła.'),

                        Toggle::make('email_verified_at')
                            ->label('Email zweryfikowany')
                            ->default(true)
                            ->dehydrated(false)
                            ->afterStateUpdated(function ($state, Set $set) {
                                $set('email_verified_at', $state ? now() : null);
                            }),
                    ])
                    ->columns(2),

                Section::make('Uprawnienia')
                    ->schema([
                        Select::make('role')
                            ->label('Rola')
                            ->options(function () {
                                return Role::where('guard_name', 'web')
                                    ->pluck('name', 'name')
                                    ->toArray();
                            })
                            ->required()
                            ->default('tenant_admin')
                            ->helperText('Wybierz rolę użytkownika. Super Admin ma dostęp do wszystkich tenantów.'),

                        Toggle::make('is_super_admin')
                            ->label('Super Administrator')
                            ->default(false)
                            ->helperText('Super admin ma dostęp do wszystkich tenantów i pełne uprawnienia.')
                            ->visible(fn ($get) => $get('role') === 'super_admin')
                            ->afterStateUpdated(function ($state, Set $set) {
                                if ($state) {
                                    // Przypisz do system tenant (Tenant 0)
                                    $systemTenant = Tenant::getSystemTenant();
                                    if ($systemTenant) {
                                        $set('tenant_id', $systemTenant->id);
                                    }
                                }
                            }),

                        Select::make('tenant_id')
                            ->label('Tenant')
                            ->relationship('tenant', 'name', fn ($query) => $query->where('slug', '!=', 'system'))
                            ->searchable()
                            ->preload()
                            ->required(fn (Get $get) => $get('role') !== 'super_admin')
                            ->visible(fn (Get $get) => $get('role') !== 'super_admin')
                            ->helperText('Wybierz tenant dla użytkownika. Super Admin jest automatycznie przypisany do Tenant System.'),

                        Toggle::make('send_invitation')
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
                TextColumn::make('name')
                    ->label('Imię i nazwisko')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->sortable()
                    ->copyable(),

                TextColumn::make('roles.name')
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

                IconColumn::make('is_super_admin')
                    ->label('Super Admin')
                    ->boolean()
                    ->sortable(),

                TextColumn::make('tenant.name')
                    ->label('Tenant')
                    ->searchable()
                    ->sortable()
                    ->default('—'),

                IconColumn::make('email_verified_at')
                    ->label('Email zweryfikowany')
                    ->boolean()
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Utworzono')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('role')
                    ->label('Rola')
                    ->relationship('roles', 'name')
                    ->multiple(),

                TernaryFilter::make('is_super_admin')
                    ->label('Super Admin')
                    ->placeholder('Wszyscy')
                    ->trueLabel('Tylko Super Admini')
                    ->falseLabel('Bez Super Adminów'),

                SelectFilter::make('tenant_id')
                    ->label('Tenant')
                    ->relationship('tenant', 'name')
                    ->searchable()
                    ->preload(),

                TernaryFilter::make('email_verified_at')
                    ->label('Email zweryfikowany')
                    ->placeholder('Wszyscy')
                    ->trueLabel('Zweryfikowani')
                    ->falseLabel('Niezwerfikowani'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
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
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['roles', 'tenant']);
    }
}

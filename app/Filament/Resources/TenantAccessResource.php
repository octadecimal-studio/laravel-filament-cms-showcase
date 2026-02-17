<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\TenantAccessResource\Pages;
use App\Modules\Core\Models\Tenant;
use App\Modules\Core\Models\TenantFeatureAccess;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Filament Resource do zarządzania dostępami tenantów do funkcjonalności.
 *
 * Panel administracyjny umożliwiający super adminowi przydzielanie
 * granularnych uprawnień (CRUD) dla każdej funkcjonalności dostępnej
 * w systemie.
 */
final class TenantAccessResource extends Resource
{
    /**
     * Model.
     */
    protected static ?string $model = Tenant::class;

    /**
     * Ikona w nawigacji.
     */
    protected static ?string $navigationIcon = 'heroicon-o-key';

    /**
     * Etykieta nawigacji.
     */
    protected static ?string $navigationLabel = 'Dostępy klientów';

    /**
     * Etykieta modelu (liczba pojedyncza).
     */
    protected static ?string $modelLabel = 'Dostęp klienta';

    /**
     * Etykieta modelu (liczba mnoga).
     */
    protected static ?string $pluralModelLabel = 'Dostępy klientów';

    /**
     * Grupa nawigacji.
     */
    protected static ?string $navigationGroup = 'Administracja';

    /**
     * Kolejność w nawigacji.
     */
    protected static ?int $navigationSort = 100;

    /**
     * Slug URL.
     */
    protected static ?string $slug = 'tenant-access';

    /**
     * Tylko super admin widzi panel dostępów.
     */
    public static function shouldRegisterNavigation(): bool
    {
        $user = auth()->user();
        return $user && ($user->is_super_admin || $user->hasRole('super_admin'));
    }

    /**
     * Formularz - nie używamy standardowego formularza.
     */
    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    /**
     * Tabela z listą tenantów.
     */
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nazwa klienta')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('slug')
                    ->label('Slug')
                    ->searchable()
                    ->copyable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('plan')
                    ->label('Plan')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'enterprise' => 'success',
                        'pro' => 'info',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('feature_access_count')
                    ->label('Aktywne dostępy')
                    ->state(function (Tenant $record): int {
                        return $record->featureAccess()
                            ->where('can_view', true)
                            ->count();
                    })
                    ->badge()
                    ->color('success'),

                Tables\Columns\TextColumn::make('users_count')
                    ->label('Użytkownicy')
                    ->counts('users')
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Aktywny')
                    ->boolean(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Utworzono')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('plan')
                    ->label('Plan')
                    ->options(Tenant::PLANS),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Aktywny')
                    ->placeholder('Wszyscy')
                    ->trueLabel('Aktywni')
                    ->falseLabel('Nieaktywni'),
            ])
            ->actions([
                Tables\Actions\Action::make('manage_access')
                    ->label('Zarządzaj dostępami')
                    ->icon('heroicon-o-cog-6-tooth')
                    ->url(fn (Tenant $record): string => static::getUrl('manage', ['record' => $record]))
                    ->color('primary'),
            ])
            ->bulkActions([])
            ->defaultSort('name');
    }

    /**
     * Relacje.
     *
     * @return array<class-string>
     */
    public static function getRelations(): array
    {
        return [];
    }

    /**
     * Strony.
     *
     * @return array<string, \Filament\Resources\Pages\PageRegistration>
     */
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTenantAccess::route('/'),
            'manage' => Pages\ManageTenantAccess::route('/{record}/manage'),
        ];
    }

    /**
     * Query builder.
     *
     * @return Builder<Tenant>
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withCount('users');
    }
}

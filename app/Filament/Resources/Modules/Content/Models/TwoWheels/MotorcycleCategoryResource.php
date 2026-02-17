<?php

declare(strict_types=1);

namespace App\Filament\Resources\Modules\Content\Models\TwoWheels;

use App\Filament\Resources\Modules\Content\Models\TwoWheels\MotorcycleCategoryResource\Pages;
use App\Filament\Resources\Modules\Content\Models\TwoWheels\MotorcycleCategoryResource\RelationManagers;
use App\Modules\Content\Models\TwoWheels\MotorcycleCategory;
use App\Modules\Core\Traits\HasFeatureAccess;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

/**
 * Filament Resource dla zarządzania kategoriami motocykli.
 */
final class MotorcycleCategoryResource extends Resource
{
    use HasFeatureAccess;

    protected static ?string $model = MotorcycleCategory::class;

    protected static ?string $navigationIcon = 'heroicon-o-tag';

    protected static ?string $navigationLabel = 'Kategorie';

    protected static ?string $modelLabel = 'Kategoria';

    protected static ?string $pluralModelLabel = 'Kategorie';

    protected static ?string $navigationGroup = 'MotoRent Demo';

    /**
     * Nazwa funkcjonalności dla systemu dostępów.
     */
    protected static string $featureName = 'motorcycle_categories';

    /**
     * Sprawdza czy Resource powinien być widoczny w nawigacji.
     */
    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccessFeature();
    }

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

                        Forms\Components\Textarea::make('description')
                            ->label('Opis')
                            ->rows(3)
                            ->columnSpanFull(),

                        Forms\Components\ColorPicker::make('color')
                            ->label('Kolor')
                            ->default('#3B82F6')
                            ->helperText('Kolor kategorii'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Status')
                    ->schema([
                        Forms\Components\Toggle::make('published')
                            ->label('Opublikowany')
                            ->default(false),

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
                        $base = url('/api/motorent/categories');
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

                Tables\Columns\TextColumn::make('slug')
                    ->label('Slug')
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('description')
                    ->label('Opis')
                    ->limit(50)
                    ->toggleable(),

                Tables\Columns\ColorColumn::make('color')
                    ->label('Kolor')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('motorcycles_count')
                    ->label('Motocykle')
                    ->counts('motorcycles')
                    ->sortable(),

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
            'index' => Pages\ListMotorcycleCategories::route('/'),
            'create' => Pages\CreateMotorcycleCategory::route('/create'),
            'edit' => Pages\EditMotorcycleCategory::route('/{record}/edit'),
        ];
    }
}

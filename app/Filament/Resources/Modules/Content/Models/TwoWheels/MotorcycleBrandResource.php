<?php

declare(strict_types=1);

namespace App\Filament\Resources\Modules\Content\Models\TwoWheels;

use App\Filament\Resources\Modules\Content\Models\TwoWheels\MotorcycleBrandResource\Pages;
use App\Filament\Resources\Modules\Content\Models\TwoWheels\MotorcycleBrandResource\RelationManagers;
use App\Modules\Content\Models\TwoWheels\MotorcycleBrand;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

/**
 * Filament Resource dla zarządzania markami motocykli.
 */
final class MotorcycleBrandResource extends Resource
{
    protected static ?string $model = MotorcycleBrand::class;

    protected static ?string $navigationIcon = 'heroicon-o-star';

    protected static ?string $navigationLabel = 'Marki';

    protected static ?string $modelLabel = 'Marka';

    protected static ?string $pluralModelLabel = 'Marki';

    protected static ?string $navigationGroup = 'MotoRent Demo';

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

                        Forms\Components\Select::make('logo_id')
                            ->label('Logo')
                            ->relationship('logo', 'file_name', fn ($query) => $query->where('mime_type', 'like', 'image/%'))
                            ->searchable()
                            ->preload()
                            ->getOptionLabelFromRecordUsing(fn ($record) => $record->file_name . ' (' . ($record->collection ?? 'brak') . ')')
                            ->helperText('Wybierz logo z biblioteki mediów'),
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
                        $base = url('/api/motorent/brands');
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

                Tables\Columns\ImageColumn::make('logo.file_path')
                    ->label('Logo')
                    ->height(40)
                    ->width(40)
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
            'index' => Pages\ListMotorcycleBrands::route('/'),
            'create' => Pages\CreateMotorcycleBrand::route('/create'),
            'edit' => Pages\EditMotorcycleBrand::route('/{record}/edit'),
        ];
    }
}

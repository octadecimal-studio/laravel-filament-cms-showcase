<?php

declare(strict_types=1);

namespace App\Filament\Resources\Modules\Content\Models\TwoWheels;

use App\Modules\Core\Scopes\TenantScope;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\DateTimePicker;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Actions\Action;
use App\Modules\Core\Models\Tenant;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use App\Filament\Resources\Modules\Content\Models\TwoWheels\MotorcycleBrandResource\Pages\ListMotorcycleBrands;
use App\Filament\Resources\Modules\Content\Models\TwoWheels\MotorcycleBrandResource\Pages\CreateMotorcycleBrand;
use App\Filament\Resources\Modules\Content\Models\TwoWheels\MotorcycleBrandResource\Pages\EditMotorcycleBrand;
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

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-star';

    protected static ?string $navigationLabel = 'Marki';

    protected static ?string $modelLabel = 'Marka';

    protected static ?string $pluralModelLabel = 'Marki';

    protected static ?int $navigationSort = 30;

    /**
     * Filtruj dane po tenant_id.
     */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
                TenantScope::class,
            ]);

        $user = auth()->user();
        if ($user && !($user->is_super_admin || $user->hasRole('super_admin'))) {
            $query->where('tenant_id', $user->tenant_id);
        }
        return $query;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Podstawowe informacje')
                    ->schema([
                        TextInput::make('name')
                            ->label('Nazwa')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),

                        TextInput::make('slug')
                            ->label('Slug')
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->helperText('URL-friendly identyfikator (generowany automatycznie)'),

                        Textarea::make('description')
                            ->label('Opis')
                            ->rows(3)
                            ->columnSpanFull(),

                        Select::make('logo_id')
                            ->label('Logo')
                            ->relationship('logo', 'file_name', fn ($query) => $query->where('mime_type', 'like', 'image/%'))
                            ->searchable()
                            ->preload()
                            ->getOptionLabelFromRecordUsing(fn ($record) => $record->file_name . ' (' . ($record->collection ?? 'brak') . ')')
                            ->helperText('Wybierz logo z biblioteki mediów'),
                    ])
                    ->columns(2),

                Section::make('Status')
                    ->schema([
                        Toggle::make('published')
                            ->label('Opublikowany')
                            ->default(false),

                        DateTimePicker::make('published_at')
                            ->label('Data publikacji')
                            ->visible(fn (Get $get): bool => $get('published') === true),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->headerActions([
                Action::make('view_api')
                    ->label('Zobacz API')
                    ->icon('heroicon-o-code-bracket')
                    ->url(function () {
                        $u = auth()->user();
                        $base = url('/api/motorent/brands');
                        if ($u?->isSuperAdmin()) {
                            return $base;
                        }
                        return $base . '?tenant_id=' . ($u?->tenant_id ?? Tenant::where('slug', 'demo-studio')->where('is_active', true)->value('id') ?? '');
                    })
                    ->openUrlInNewTab()
                    ->color('info'),
            ])
            ->columns([
                TextColumn::make('name')
                    ->label('Nazwa')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('slug')
                    ->label('Slug')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('description')
                    ->label('Opis')
                    ->limit(50)
                    ->toggleable(),

                ImageColumn::make('logo.file_path')
                    ->label('Logo')
                    ->height(40)
                    ->width(40)
                    ->toggleable(),

                TextColumn::make('motorcycles_count')
                    ->label('Motocykle')
                    ->counts('motorcycles')
                    ->sortable(),

                IconColumn::make('published')
                    ->label('Opublikowany')
                    ->boolean()
                    ->toggleable(),

                TextColumn::make('created_at')
                    ->label('Utworzono')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('published')
                    ->label('Opublikowany')
                    ->placeholder('Wszystkie')
                    ->trueLabel('Tak')
                    ->falseLabel('Nie'),

                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
                RestoreAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    RestoreBulkAction::make(),
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
            'index' => ListMotorcycleBrands::route('/'),
            'create' => CreateMotorcycleBrand::route('/create'),
            'edit' => EditMotorcycleBrand::route('/{record}/edit'),
        ];
    }
}

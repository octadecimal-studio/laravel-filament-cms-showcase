<?php

declare(strict_types=1);

namespace App\Filament\Resources\Modules\Content\Models\TwoWheels;

use App\Modules\Core\Scopes\TenantScope;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use App\Filament\Resources\Modules\Content\Models\TwoWheels\RentalConditionResource\Pages\ListRentalConditions;
use App\Filament\Resources\Modules\Content\Models\TwoWheels\RentalConditionResource\Pages\CreateRentalCondition;
use App\Filament\Resources\Modules\Content\Models\TwoWheels\RentalConditionResource\Pages\EditRentalCondition;
use App\Filament\Resources\Modules\Content\Models\TwoWheels\RentalConditionResource\Pages;
use App\Modules\Content\Models\TwoWheels\RentalCondition;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

/**
 * Filament Resource dla zarządzania warunkami wypożyczenia.
 */
final class RentalConditionResource extends Resource
{
    protected static ?string $model = RentalCondition::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationLabel = 'Warunki wypożyczenia';

    protected static ?string $modelLabel = 'Warunek wypożyczenia';

    protected static ?string $pluralModelLabel = 'Warunki wypożyczenia';

    protected static ?int $navigationSort = 15;

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
                        TextInput::make('title')
                            ->label('Tytuł')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),

                        RichEditor::make('description')
                            ->label('Opis')
                            ->required()
                            ->toolbarButtons([
                                'bold', 'italic', 'underline', 'strike',
                                'h2', 'h3', 'bulletList', 'orderedList', 'blockquote', 'link',
                            ])
                            ->columnSpanFull(),

                        Select::make('icon')
                            ->label('Ikona')
                            ->options([
                                'heroicon-o-document-text' => 'Dokument',
                                'heroicon-o-banknotes' => 'Banknoty (Kaucja)',
                                'heroicon-o-shield-check' => 'Tarcza (Ubezpieczenie)',
                                'heroicon-o-identification' => 'Identyfikacja',
                                'heroicon-o-clipboard-document-list' => 'Lista dokumentów',
                                'heroicon-o-clock' => 'Zegar',
                                'heroicon-o-exclamation-triangle' => 'Ostrzeżenie',
                                'heroicon-o-check-circle' => 'Zatwierdzenie',
                                'heroicon-o-information-circle' => 'Informacja',
                                'heroicon-o-key' => 'Klucz',
                            ])
                            ->searchable()
                            ->nullable(),

                        TextInput::make('sort_order')
                            ->label('Kolejność')
                            ->numeric()
                            ->default(0)
                            ->required(),

                        Toggle::make('is_active')
                            ->label('Aktywny')
                            ->default(true),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('sort_order')
                    ->label('#')
                    ->sortable()
                    ->badge(),

                TextColumn::make('title')
                    ->label('Tytuł')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('icon')
                    ->label('Ikona')
                    ->toggleable(),

                IconColumn::make('is_active')
                    ->label('Aktywny')
                    ->boolean()
                    ->toggleable(),

                TextColumn::make('created_at')
                    ->label('Utworzono')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Aktywny')
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
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRentalConditions::route('/'),
            'create' => CreateRentalCondition::route('/create'),
            'edit' => EditRentalCondition::route('/{record}/edit'),
        ];
    }
}

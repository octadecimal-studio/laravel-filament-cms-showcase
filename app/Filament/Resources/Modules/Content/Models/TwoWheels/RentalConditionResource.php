<?php

declare(strict_types=1);

namespace App\Filament\Resources\Modules\Content\Models\TwoWheels;

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

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationLabel = 'Warunki wypożyczenia';

    protected static ?string $modelLabel = 'Warunek wypożyczenia';

    protected static ?string $pluralModelLabel = 'Warunki wypożyczenia';

    protected static ?int $navigationSort = 15;

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
                        Forms\Components\TextInput::make('title')
                            ->label('Tytuł')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),

                        Forms\Components\RichEditor::make('description')
                            ->label('Opis')
                            ->required()
                            ->toolbarButtons([
                                'bold', 'italic', 'underline', 'strike',
                                'h2', 'h3', 'bulletList', 'orderedList', 'blockquote', 'link',
                            ])
                            ->columnSpanFull(),

                        Forms\Components\Select::make('icon')
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

                        Forms\Components\TextInput::make('sort_order')
                            ->label('Kolejność')
                            ->numeric()
                            ->default(0)
                            ->required(),

                        Forms\Components\Toggle::make('is_active')
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
                Tables\Columns\TextColumn::make('sort_order')
                    ->label('#')
                    ->sortable()
                    ->badge(),

                Tables\Columns\TextColumn::make('title')
                    ->label('Tytuł')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('icon')
                    ->label('Ikona')
                    ->toggleable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Aktywny')
                    ->boolean()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Utworzono')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Aktywny')
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
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRentalConditions::route('/'),
            'create' => Pages\CreateRentalCondition::route('/create'),
            'edit' => Pages\EditRentalCondition::route('/{record}/edit'),
        ];
    }
}

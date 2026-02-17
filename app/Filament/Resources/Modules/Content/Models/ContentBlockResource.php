<?php

declare(strict_types=1);

namespace App\Filament\Resources\Modules\Content\Models;

use App\Filament\Concerns\HasGlobalBulkActions;
use App\Filament\Concerns\RemembersTableSettings;
use App\Filament\Resources\Modules\Content\Models\ContentBlockResource\Pages;
use App\Modules\Content\Models\ContentBlock;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

/**
 * Filament Resource dla zarządzania blokami treści.
 */
final class ContentBlockResource extends Resource
{
    use HasGlobalBulkActions;
    use RemembersTableSettings;

    protected static ?string $model = ContentBlock::class;

    protected static ?string $navigationIcon = 'heroicon-o-squares-2x2';

    protected static ?string $navigationLabel = 'Bloki treści';

    protected static ?string $modelLabel = 'Blok treści';

    protected static ?string $pluralModelLabel = 'Bloki treści';

    protected static ?string $navigationGroup = 'Content';

    /**
     * Tylko super admin widzi bloki treści.
     */
    public static function shouldRegisterNavigation(): bool
    {
        $user = auth()->user();
        return $user && ($user->is_super_admin || $user->hasRole('super_admin'));
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
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->helperText('Unikalny identyfikator bloku'),

                        Forms\Components\TextInput::make('category')
                            ->label('Kategoria')
                            ->maxLength(255)
                            ->helperText('np. hero, features, cta'),

                        Forms\Components\Textarea::make('description')
                            ->label('Opis')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Schema i dane')
                    ->schema([
                        Forms\Components\KeyValue::make('schema')
                            ->label('JSON Schema')
                            ->keyLabel('Klucz')
                            ->valueLabel('Wartość')
                            ->columnSpanFull()
                            ->helperText('Definicja pól bloku (JSON Schema)'),

                        Forms\Components\KeyValue::make('default_data')
                            ->label('Domyślne dane')
                            ->keyLabel('Klucz')
                            ->valueLabel('Wartość')
                            ->columnSpanFull(),

                        Forms\Components\KeyValue::make('config')
                            ->label('Konfiguracja')
                            ->keyLabel('Klucz')
                            ->valueLabel('Wartość')
                            ->columnSpanFull(),
                    ])
                    ->collapsible(),

                Forms\Components\Section::make('Status')
                    ->schema([
                        Forms\Components\Toggle::make('is_active')
                            ->label('Aktywny')
                            ->default(true),
                    ])
                    ->columns(1),
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
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('slug')
                    ->label('Slug')
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('category')
                    ->label('Kategoria')
                    ->badge()
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Aktywny')
                    ->boolean(),

                Tables\Columns\TextColumn::make('usage_count')
                    ->label('Użycia')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Utworzono')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('category')
                    ->label('Kategoria')
                    ->options(function (): array {
                        // Pobierz wszystkie unikalne kategorie z bazy danych
                        $categories = ContentBlock::query()
                            ->whereNotNull('category')
                            ->distinct()
                            ->pluck('category', 'category')
                            ->toArray();
                        
                        return $categories;
                    })
                    ->searchable()
                    ->native(false),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Aktywny')
                    ->placeholder('Wszystkie')
                    ->trueLabel('Tak')
                    ->falseLabel('Nie'),

                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
                Tables\Actions\RestoreAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                    Tables\Actions\ForceDeleteBulkAction::make(),
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
            'index' => Pages\ListContentBlocks::route('/'),
            'create' => Pages\CreateContentBlock::route('/create'),
            'view' => Pages\ViewContentBlock::route('/{record}'),
            'edit' => Pages\EditContentBlock::route('/{record}/edit'),
        ];
    }

    /**
     * Query builder z domyślnymi filtrami.
     *
     * @return Builder<ContentBlock>
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}

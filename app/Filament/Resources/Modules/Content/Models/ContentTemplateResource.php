<?php

declare(strict_types=1);

namespace App\Filament\Resources\Modules\Content\Models;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Toggle;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Actions\ViewAction;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ForceDeleteBulkAction;
use App\Filament\Resources\Modules\Content\Models\ContentTemplateResource\Pages\ListContentTemplates;
use App\Filament\Resources\Modules\Content\Models\ContentTemplateResource\Pages\CreateContentTemplate;
use App\Filament\Resources\Modules\Content\Models\ContentTemplateResource\Pages\ViewContentTemplate;
use App\Filament\Resources\Modules\Content\Models\ContentTemplateResource\Pages\EditContentTemplate;
use App\Filament\Concerns\HasGlobalBulkActions;
use App\Filament\Concerns\RemembersTableSettings;
use App\Filament\Resources\Modules\Content\Models\ContentTemplateResource\Pages;
use App\Modules\Content\Models\ContentTemplate;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

/**
 * Filament Resource dla zarządzania szablonami treści.
 */
final class ContentTemplateResource extends Resource
{
    use HasGlobalBulkActions;
    use RemembersTableSettings;

    protected static ?string $model = ContentTemplate::class;

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-document-duplicate';

    protected static ?string $navigationLabel = 'Szablony';

    protected static ?string $modelLabel = 'Szablon';

    protected static ?string $pluralModelLabel = 'Szablony';

    protected static string | \UnitEnum | null $navigationGroup = 'Content';

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
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->helperText('Unikalny identyfikator szablonu'),

                        Select::make('category')
                            ->label('Kategoria')
                            ->options([
                                'page' => 'Strona',
                                'section' => 'Sekcja',
                                'email' => 'Email',
                            ])
                            ->native(false),

                        Textarea::make('description')
                            ->label('Opis')
                            ->rows(3)
                            ->columnSpanFull(),

                        TextInput::make('thumbnail_url')
                            ->label('URL miniaturki')
                            ->url()
                            ->maxLength(255)
                            ->columnSpanFull(),

                        TagsInput::make('tags')
                            ->label('Tagi')
                            ->separator(',')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Section::make('Struktura i dane')
                    ->schema([
                        KeyValue::make('structure')
                            ->label('Struktura (JSON)')
                            ->keyLabel('Klucz')
                            ->valueLabel('Wartość')
                            ->columnSpanFull()
                            ->helperText('Struktura bloków i layoutu'),

                        KeyValue::make('default_data')
                            ->label('Domyślne dane')
                            ->keyLabel('Klucz')
                            ->valueLabel('Wartość')
                            ->columnSpanFull(),

                        KeyValue::make('config')
                            ->label('Konfiguracja')
                            ->keyLabel('Klucz')
                            ->valueLabel('Wartość')
                            ->columnSpanFull(),
                    ])
                    ->collapsible(),

                Section::make('Status i ocena')
                    ->schema([
                        Toggle::make('is_active')
                            ->label('Aktywny')
                            ->default(true),

                        Toggle::make('is_premium')
                            ->label('Premium')
                            ->default(false),

                        TextInput::make('rating')
                            ->label('Ocena')
                            ->numeric()
                            ->step(0.01)
                            ->minValue(0)
                            ->maxValue(5)
                            ->helperText('Ocena 0.00-5.00'),
                    ])
                    ->columns(3),
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
                TextColumn::make('name')
                    ->label('Nazwa')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('slug')
                    ->label('Slug')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('category')
                    ->label('Kategoria')
                    ->badge()
                    ->searchable()
                    ->toggleable(),

                IconColumn::make('is_active')
                    ->label('Aktywny')
                    ->boolean(),

                IconColumn::make('is_premium')
                    ->label('Premium')
                    ->boolean(),

                TextColumn::make('rating')
                    ->label('Ocena')
                    ->sortable()
                    ->formatStateUsing(fn (?float $state): string => $state ? number_format($state, 2) : '-')
                    ->toggleable(),

                TextColumn::make('usage_count')
                    ->label('Użycia')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('created_at')
                    ->label('Utworzono')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('category')
                    ->label('Kategoria')
                    ->options([
                        'page' => 'Strona',
                        'section' => 'Sekcja',
                        'email' => 'Email',
                    ])
                    ->native(false),

                TernaryFilter::make('is_active')
                    ->label('Aktywny')
                    ->placeholder('Wszystkie')
                    ->trueLabel('Tak')
                    ->falseLabel('Nie'),

                TernaryFilter::make('is_premium')
                    ->label('Premium')
                    ->placeholder('Wszystkie')
                    ->trueLabel('Tak')
                    ->falseLabel('Nie'),

                TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
                RestoreAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                    ForceDeleteBulkAction::make(),
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
            'index' => ListContentTemplates::route('/'),
            'create' => CreateContentTemplate::route('/create'),
            'view' => ViewContentTemplate::route('/{record}'),
            'edit' => EditContentTemplate::route('/{record}/edit'),
        ];
    }

    /**
     * Query builder z domyślnymi filtrami.
     *
     * @return Builder<ContentTemplate>
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}

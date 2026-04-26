<?php

declare(strict_types=1);

namespace App\Filament\Resources\Modules\Content\Models;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Actions\ViewAction;
use Filament\Actions\EditAction;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\BulkAction;
use App\Filament\Resources\Modules\Content\Models\SiteContentResource\Pages\ListSiteContents;
use App\Filament\Resources\Modules\Content\Models\SiteContentResource\Pages\CreateSiteContent;
use App\Filament\Resources\Modules\Content\Models\SiteContentResource\Pages\ViewSiteContent;
use App\Filament\Resources\Modules\Content\Models\SiteContentResource\Pages\EditSiteContent;
use App\Filament\Concerns\HasGlobalBulkActions;
use App\Filament\Concerns\RemembersTableSettings;
use App\Filament\Resources\Modules\Content\Models\SiteContentResource\Pages;
use App\Modules\Content\Models\ContentBlock;
use App\Modules\Content\Models\SiteContent;
use App\Modules\Content\Services\ContentBlockFormBuilder;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;

/**
 * Filament Resource dla zarządzania treściami CMS.
 */
final class SiteContentResource extends Resource
{
    use HasGlobalBulkActions;
    use RemembersTableSettings;

    protected static ?string $model = SiteContent::class;

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationLabel = 'Treści';

    protected static ?string $modelLabel = 'Treść';

    protected static ?string $pluralModelLabel = 'Treści';

    protected static string | \UnitEnum | null $navigationGroup = 'Content';

    /**
     * Formularz edycji/tworzenia treści.
     */
    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Podstawowe informacje')
                    ->schema([
                        Select::make('site_id')
                            ->label('Strona')
                            ->relationship('site', 'name')
                            ->searchable()
                            ->preload()
                            ->helperText('Strona do której należy treść'),

                        Select::make('type')
                            ->label('Typ')
                            ->options([
                                'page' => 'Strona',
                                'section' => 'Sekcja',
                                'component' => 'Komponent',
                                'block' => 'Blok',
                            ])
                            ->required()
                            ->default('page')
                            ->native(false)
                            ->reactive(),

                        TextInput::make('title')
                            ->label('Tytuł')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),

                        TextInput::make('slug')
                            ->label('Slug')
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->visible(fn (Get $get) => $get('type') === 'page')
                            ->helperText('URL-friendly identyfikator strony'),

                        Textarea::make('description')
                            ->label('Opis')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Section::make('Content Block')
                    ->schema([
                        Select::make('content_block_id')
                            ->label('Content Block')
                            ->relationship('contentBlock', 'name', fn ($query) => $query->where('is_active', true))
                            ->searchable()
                            ->preload()
                            ->reactive()
                            ->afterStateUpdated(function (Set $set, ?string $state) {
                                if ($state) {
                                    $block = ContentBlock::find($state);
                                    if ($block && $block->default_data) {
                                        // Ustaw domyślne dane z ContentBlock
                                        $set('data', $block->default_data);
                                        $set('title', $block->name);
                                        $set('type', 'block');
                                    }
                                }
                            })
                            ->helperText('Wybierz ContentBlock aby użyć jego schema do edycji danych')
                            ->columnSpanFull()
                            ->visible(fn (Get $get) => $get('type') === 'block' || $get('type') === 'section'),
                    ])
                    ->collapsible()
                    ->collapsed()
                    ->visible(fn (Get $get) => $get('type') === 'block' || $get('type') === 'section'),

                Section::make('Dane Content Block')
                    ->schema(function (Get $get, ?SiteContent $record) {
                        $contentBlockId = $get('content_block_id');
                        
                        if (! $contentBlockId) {
                            return [
                                Placeholder::make('select_block')
                                    ->label('')
                                    ->content('Wybierz ContentBlock powyżej aby zobaczyć formularz')
                                    ->columnSpanFull(),
                            ];
                        }

                        $contentBlock = ContentBlock::find($contentBlockId);
                        if (! $contentBlock) {
                            return [
                                Placeholder::make('block_not_found')
                                    ->label('')
                                    ->content('ContentBlock nie został znaleziony')
                                    ->columnSpanFull(),
                            ];
                        }

                        $builder = app(ContentBlockFormBuilder::class);
                        $currentData = $record?->data ?? $contentBlock->default_data ?? [];
                        
                        return $builder->buildForm($contentBlock, $currentData);
                    })
                    ->visible(fn (Get $get) => ($get('type') === 'block' || $get('type') === 'section') && $get('content_block_id'))
                    ->columnSpanFull(),

                Section::make('Treść (JSON)')
                    ->schema([
                        KeyValue::make('data')
                            ->label('Dane treści (JSON)')
                            ->keyLabel('Klucz')
                            ->valueLabel('Wartość')
                            ->columnSpanFull()
                            ->helperText('Flexible content - struktura JSON (używaj gdy nie wybrano ContentBlock)')
                            ->visible(fn (Get $get) => ! $get('content_block_id')),

                        KeyValue::make('meta')
                            ->label('Meta dane (SEO)')
                            ->keyLabel('Klucz')
                            ->valueLabel('Wartość')
                            ->columnSpanFull()
                            ->helperText('SEO meta tags, OG tags, itp.'),
                    ])
                    ->visible(fn (Get $get) => $get('type') !== 'block' && $get('type') !== 'section'),

                Section::make('Status i publikacja')
                    ->schema([
                        Select::make('status')
                            ->label('Status')
                            ->options([
                                'draft' => 'Szkic',
                                'published' => 'Opublikowane',
                                'archived' => 'Zarchiwizowane',
                            ])
                            ->required()
                            ->default('draft')
                            ->native(false),

                        DateTimePicker::make('published_at')
                            ->label('Data publikacji')
                            ->visible(fn (Get $get) => $get('status') === 'published'),

                        TextInput::make('order')
                            ->label('Kolejność')
                            ->numeric()
                            ->default(0)
                            ->helperText('Sortowanie (niższa wartość = wyżej)'),
                    ])
                    ->columns(3),

                Section::make('Hierarchia')
                    ->schema([
                        Select::make('parent_id')
                            ->label('Rodzic')
                            ->relationship('parent', 'title')
                            ->searchable()
                            ->preload()
                            ->native(false)
                            ->helperText('Opcjonalnie: przypisz do innej treści (hierarchia)'),
                    ])
                    ->collapsible()
                    ->collapsed(),

                Section::make('Wersjonowanie')
                    ->schema([
                        Toggle::make('is_current_version')
                            ->label('Aktualna wersja')
                            ->default(true)
                            ->helperText('Czy to jest aktualna wersja treści?'),

                        TextInput::make('version')
                            ->label('Numer wersji')
                            ->numeric()
                            ->default(1)
                            ->disabled()
                            ->helperText('Automatycznie zwiększany przy tworzeniu nowej wersji'),
                    ])
                    ->columns(2)
                    ->collapsible()
                    ->collapsed(),
            ]);
    }

    /**
     * Tabela listy treści.
     */
    public static function table(Table $table): Table
    {
        // Zapamiętaj ustawienia w sesji (automatyczne klucze)
        $table->persistSortInSession()
            ->persistFiltersInSession()
            ->persistSearchInSession()
            ->persistColumnSearchesInSession();
        
        $table = $table
            ->columns([
                TextColumn::make('title')
                    ->label('Tytuł')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('type')
                    ->label('Typ')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'page' => 'success',
                        'section' => 'info',
                        'component' => 'warning',
                        'block' => 'gray',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'page' => 'Strona',
                        'section' => 'Sekcja',
                        'component' => 'Komponent',
                        'block' => 'Blok',
                        default => $state,
                    }),

                TextColumn::make('contentBlock.name')
                    ->label('Content Block')
                    ->badge()
                    ->searchable()
                    ->sortable()
                    ->toggleable()
                    ->placeholder('Brak'),

                TextColumn::make('slug')
                    ->label('Slug')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'published' => 'success',
                        'draft' => 'gray',
                        'archived' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'published' => 'Opublikowane',
                        'draft' => 'Szkic',
                        'archived' => 'Zarchiwizowane',
                        default => $state,
                    }),

                IconColumn::make('is_current_version')
                    ->label('Aktualna wersja')
                    ->boolean()
                    ->toggleable(),

                TextColumn::make('version')
                    ->label('Wersja')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('published_at')
                    ->label('Opublikowano')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('Utworzono')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->label('Zaktualizowano')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label('Typ')
                    ->options([
                        'page' => 'Strona',
                        'section' => 'Sekcja',
                        'component' => 'Komponent',
                        'block' => 'Blok',
                    ])
                    ->native(false),

                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'draft' => 'Szkic',
                        'published' => 'Opublikowane',
                        'archived' => 'Zarchiwizowane',
                    ])
                    ->native(false),

                TernaryFilter::make('is_current_version')
                    ->label('Aktualna wersja')
                    ->placeholder('Wszystkie')
                    ->trueLabel('Tak')
                    ->falseLabel('Nie'),

                TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                Action::make('publish')
                    ->label('Opublikuj')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(function (SiteContent $record): void {
                        $record->update([
                            'status' => 'published',
                            'published_at' => now(),
                        ]);
                    })
                    ->visible(fn (?SiteContent $record): bool => $record?->status !== 'published'),
                Action::make('archive')
                    ->label('Archiwizuj')
                    ->icon('heroicon-o-archive-box')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function (SiteContent $record): void {
                        $record->update(['status' => 'archived']);
                    })
                    ->visible(fn (?SiteContent $record): bool => $record?->status !== 'archived'),
                DeleteAction::make(),
                RestoreAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    BulkAction::make('publish')
                        ->label('Opublikuj zaznaczone')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(function ($records): void {
                            $records->each(function (SiteContent $record): void {
                                $record->update([
                                    'status' => 'published',
                                    'published_at' => now(),
                                ]);
                            });
                        }),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
        
        // Konfiguruj bulk actions
        $table = static::configureBulkActions($table);
        
        return $table;
    }
    
    /**
     * Relacje.
     */
    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    /**
     * Strony Resource.
     */
    public static function getPages(): array
    {
        return [
            'index' => ListSiteContents::route('/'),
            'create' => CreateSiteContent::route('/create'),
            'view' => ViewSiteContent::route('/{record}'),
            'edit' => EditSiteContent::route('/{record}/edit'),
        ];
    }

    /**
     * Query builder z domyślnymi filtrami.
     *
     * @return Builder<SiteContent>
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}

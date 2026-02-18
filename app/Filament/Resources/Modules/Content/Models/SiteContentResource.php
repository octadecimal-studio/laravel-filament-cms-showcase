<?php

declare(strict_types=1);

namespace App\Filament\Resources\Modules\Content\Models;

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

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationLabel = 'Treści';

    protected static ?string $modelLabel = 'Treść';

    protected static ?string $pluralModelLabel = 'Treści';

    protected static ?string $navigationGroup = 'Content';

    /**
     * Formularz edycji/tworzenia treści.
     */
    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Podstawowe informacje')
                    ->schema([
                        Forms\Components\Select::make('site_id')
                            ->label('Strona')
                            ->relationship('site', 'name')
                            ->searchable()
                            ->preload()
                            ->helperText('Strona do której należy treść'),

                        Forms\Components\Select::make('type')
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

                        Forms\Components\TextInput::make('title')
                            ->label('Tytuł')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('slug')
                            ->label('Slug')
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->visible(fn (Forms\Get $get) => $get('type') === 'page')
                            ->helperText('URL-friendly identyfikator strony'),

                        Forms\Components\Textarea::make('description')
                            ->label('Opis')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Content Block')
                    ->schema([
                        Forms\Components\Select::make('content_block_id')
                            ->label('Content Block')
                            ->relationship('contentBlock', 'name', fn ($query) => $query->where('is_active', true))
                            ->searchable()
                            ->preload()
                            ->reactive()
                            ->afterStateUpdated(function (Forms\Set $set, ?string $state) {
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
                            ->visible(fn (Forms\Get $get) => $get('type') === 'block' || $get('type') === 'section'),
                    ])
                    ->collapsible()
                    ->collapsed()
                    ->visible(fn (Forms\Get $get) => $get('type') === 'block' || $get('type') === 'section'),

                Forms\Components\Section::make('Dane Content Block')
                    ->schema(function (Forms\Get $get, ?SiteContent $record) {
                        $contentBlockId = $get('content_block_id');
                        
                        if (! $contentBlockId) {
                            return [
                                Forms\Components\Placeholder::make('select_block')
                                    ->label('')
                                    ->content('Wybierz ContentBlock powyżej aby zobaczyć formularz')
                                    ->columnSpanFull(),
                            ];
                        }

                        $contentBlock = ContentBlock::find($contentBlockId);
                        if (! $contentBlock) {
                            return [
                                Forms\Components\Placeholder::make('block_not_found')
                                    ->label('')
                                    ->content('ContentBlock nie został znaleziony')
                                    ->columnSpanFull(),
                            ];
                        }

                        $builder = app(ContentBlockFormBuilder::class);
                        $currentData = $record?->data ?? $contentBlock->default_data ?? [];
                        
                        return $builder->buildForm($contentBlock, $currentData);
                    })
                    ->visible(fn (Forms\Get $get) => ($get('type') === 'block' || $get('type') === 'section') && $get('content_block_id'))
                    ->columnSpanFull(),

                Forms\Components\Section::make('Treść (JSON)')
                    ->schema([
                        Forms\Components\KeyValue::make('data')
                            ->label('Dane treści (JSON)')
                            ->keyLabel('Klucz')
                            ->valueLabel('Wartość')
                            ->columnSpanFull()
                            ->helperText('Flexible content - struktura JSON (używaj gdy nie wybrano ContentBlock)')
                            ->visible(fn (Forms\Get $get) => ! $get('content_block_id')),

                        Forms\Components\KeyValue::make('meta')
                            ->label('Meta dane (SEO)')
                            ->keyLabel('Klucz')
                            ->valueLabel('Wartość')
                            ->columnSpanFull()
                            ->helperText('SEO meta tags, OG tags, itp.'),
                    ])
                    ->visible(fn (Forms\Get $get) => $get('type') !== 'block' && $get('type') !== 'section'),

                Forms\Components\Section::make('Status i publikacja')
                    ->schema([
                        Forms\Components\Select::make('status')
                            ->label('Status')
                            ->options([
                                'draft' => 'Szkic',
                                'published' => 'Opublikowane',
                                'archived' => 'Zarchiwizowane',
                            ])
                            ->required()
                            ->default('draft')
                            ->native(false),

                        Forms\Components\DateTimePicker::make('published_at')
                            ->label('Data publikacji')
                            ->visible(fn (Forms\Get $get) => $get('status') === 'published'),

                        Forms\Components\TextInput::make('order')
                            ->label('Kolejność')
                            ->numeric()
                            ->default(0)
                            ->helperText('Sortowanie (niższa wartość = wyżej)'),
                    ])
                    ->columns(3),

                Forms\Components\Section::make('Hierarchia')
                    ->schema([
                        Forms\Components\Select::make('parent_id')
                            ->label('Rodzic')
                            ->relationship('parent', 'title')
                            ->searchable()
                            ->preload()
                            ->native(false)
                            ->helperText('Opcjonalnie: przypisz do innej treści (hierarchia)'),
                    ])
                    ->collapsible()
                    ->collapsed(),

                Forms\Components\Section::make('Wersjonowanie')
                    ->schema([
                        Forms\Components\Toggle::make('is_current_version')
                            ->label('Aktualna wersja')
                            ->default(true)
                            ->helperText('Czy to jest aktualna wersja treści?'),

                        Forms\Components\TextInput::make('version')
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
                Tables\Columns\TextColumn::make('title')
                    ->label('Tytuł')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('type')
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

                Tables\Columns\TextColumn::make('contentBlock.name')
                    ->label('Content Block')
                    ->badge()
                    ->searchable()
                    ->sortable()
                    ->toggleable()
                    ->placeholder('Brak'),

                Tables\Columns\TextColumn::make('slug')
                    ->label('Slug')
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('status')
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

                Tables\Columns\IconColumn::make('is_current_version')
                    ->label('Aktualna wersja')
                    ->boolean()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('version')
                    ->label('Wersja')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('published_at')
                    ->label('Opublikowano')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Utworzono')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Zaktualizowano')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->label('Typ')
                    ->options([
                        'page' => 'Strona',
                        'section' => 'Sekcja',
                        'component' => 'Komponent',
                        'block' => 'Blok',
                    ])
                    ->native(false),

                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'draft' => 'Szkic',
                        'published' => 'Opublikowane',
                        'archived' => 'Zarchiwizowane',
                    ])
                    ->native(false),

                Tables\Filters\TernaryFilter::make('is_current_version')
                    ->label('Aktualna wersja')
                    ->placeholder('Wszystkie')
                    ->trueLabel('Tak')
                    ->falseLabel('Nie'),

                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('publish')
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
                Tables\Actions\Action::make('archive')
                    ->label('Archiwizuj')
                    ->icon('heroicon-o-archive-box')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function (SiteContent $record): void {
                        $record->update(['status' => 'archived']);
                    })
                    ->visible(fn (?SiteContent $record): bool => $record?->status !== 'archived'),
                Tables\Actions\DeleteAction::make(),
                Tables\Actions\RestoreAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                    Tables\Actions\ForceDeleteBulkAction::make(),
                    Tables\Actions\BulkAction::make('publish')
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
            'index' => Pages\ListSiteContents::route('/'),
            'create' => Pages\CreateSiteContent::route('/create'),
            'view' => Pages\ViewSiteContent::route('/{record}'),
            'edit' => Pages\EditSiteContent::route('/{record}/edit'),
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

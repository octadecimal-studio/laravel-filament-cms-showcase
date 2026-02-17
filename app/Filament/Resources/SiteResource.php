<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Concerns\HasGlobalBulkActions;
use App\Filament\Concerns\RemembersTableSettings;
use App\Filament\Resources\SiteResource\Pages;
use App\Jobs\DeploySiteJob;
use App\Models\Site;
use App\Modules\Core\Traits\HasFeatureAccess;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

/**
 * Filament Resource dla stron klientów.
 */
class SiteResource extends Resource
{
    use HasFeatureAccess;
    use HasGlobalBulkActions;
    use RemembersTableSettings;

    protected static ?string $model = Site::class;

    protected static ?string $navigationIcon = 'heroicon-o-globe-alt';

    protected static ?string $navigationLabel = 'Strony';

    protected static ?string $modelLabel = 'Strona';

    protected static ?string $pluralModelLabel = 'Strony';

    protected static ?string $navigationGroup = 'CRM';

    protected static ?int $navigationSort = 2;

    /**
     * Nazwa funkcjonalności dla systemu dostępów.
     */
    protected static string $featureName = 'sites';

    /**
     * Sprawdza czy Resource powinien być widoczny w nawigacji.
     */
    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccessFeature();
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Informacje podstawowe')
                    ->schema([
                        Forms\Components\Select::make('customer_id')
                            ->label('Klient')
                            ->relationship('customer', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),

                        Forms\Components\TextInput::make('name')
                            ->label('Nazwa strony')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('slug')
                            ->label('Slug')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),

                        Forms\Components\TextInput::make('code')
                            ->label('Kod')
                            ->maxLength(20),

                        Forms\Components\Select::make('status')
                            ->label('Status')
                            ->options([
                                'draft' => 'Szkic',
                                'development' => 'W budowie',
                                'staging' => 'Staging',
                                'live' => 'Live',
                                'suspended' => 'Zawieszona',
                                'archived' => 'Zarchiwizowana',
                            ])
                            ->default('draft')
                            ->required(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Szablon')
                    ->schema([
                        Forms\Components\Select::make('template_id')
                            ->label('Szablon bazowy')
                            ->relationship('template', 'name')
                            ->searchable()
                            ->preload(),

                        Forms\Components\TextInput::make('template_slug')
                            ->label('Slug szablonu')
                            ->maxLength(100),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('URL-e')
                    ->schema([
                        Forms\Components\TextInput::make('staging_url')
                            ->label('URL Staging')
                            ->url()
                            ->maxLength(500)
                            ->suffixIcon('heroicon-o-arrow-top-right-on-square')
                            ->suffixIconColor('gray'),

                        Forms\Components\TextInput::make('production_url')
                            ->label('URL Produkcja')
                            ->url()
                            ->maxLength(500)
                            ->suffixIcon('heroicon-o-arrow-top-right-on-square')
                            ->suffixIconColor('success'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Statystyki')
                    ->schema([
                        Forms\Components\TextInput::make('pages_count')
                            ->label('Liczba stron')
                            ->numeric()
                            ->default(0)
                            ->disabled(),

                        Forms\Components\TextInput::make('media_count')
                            ->label('Liczba mediów')
                            ->numeric()
                            ->default(0)
                            ->disabled(),

                        Forms\Components\DateTimePicker::make('published_at')
                            ->label('Data publikacji')
                            ->disabled(),

                        Forms\Components\DateTimePicker::make('last_content_update_at')
                            ->label('Ostatnia aktualizacja')
                            ->disabled(),
                    ])
                    ->columns(2)
                    ->visibleOn('view'),

                Forms\Components\Section::make('Ustawienia')
                    ->schema([
                        Forms\Components\KeyValue::make('settings')
                            ->label('Ustawienia strony')
                            ->keyLabel('Klucz')
                            ->valueLabel('Wartość'),

                        Forms\Components\KeyValue::make('seo_settings')
                            ->label('Ustawienia SEO')
                            ->keyLabel('Klucz')
                            ->valueLabel('Wartość'),
                    ])
                    ->collapsible()
                    ->collapsed(),
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
                    ->sortable(),

                Tables\Columns\TextColumn::make('customer.name')
                    ->label('Klient')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->colors([
                        'gray' => 'draft',
                        'warning' => 'development',
                        'info' => 'staging',
                        'success' => 'live',
                        'danger' => 'suspended',
                        'secondary' => 'archived',
                    ]),

                Tables\Columns\TextColumn::make('staging_url')
                    ->label('Staging')
                    ->url(fn ($record) => $record->staging_url)
                    ->openUrlInNewTab()
                    ->limit(30)
                    ->toggleable(),

                Tables\Columns\TextColumn::make('production_url')
                    ->label('Produkcja')
                    ->url(fn ($record) => $record->production_url)
                    ->openUrlInNewTab()
                    ->limit(30)
                    ->toggleable(),

                Tables\Columns\TextColumn::make('template.name')
                    ->label('Szablon')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('orders_count')
                    ->label('Zlecenia')
                    ->counts('orders')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('corrections_count')
                    ->label('Poprawki')
                    ->counts('corrections')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Utworzono')
                    ->dateTime('d.m.Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'draft' => 'Szkic',
                        'development' => 'W budowie',
                        'staging' => 'Staging',
                        'live' => 'Live',
                        'suspended' => 'Zawieszona',
                        'archived' => 'Zarchiwizowana',
                    ]),

                Tables\Filters\SelectFilter::make('customer_id')
                    ->label('Klient')
                    ->relationship('customer', 'name')
                    ->searchable()
                    ->preload(),

                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('deploy_staging')
                        ->label('Deploy na Staging')
                        ->icon('heroicon-o-cloud-arrow-up')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->modalHeading('Deploy na Staging')
                        ->modalDescription('Czy na pewno chcesz wdrożyć tę stronę na środowisko staging?')
                        ->modalSubmitActionLabel('Wdróż')
                        ->form([
                            Forms\Components\Select::make('deploy_type')
                                ->label('Typ deploymentu')
                                ->options([
                                    'static' => 'Static (HTML/CSS/JS)',
                                    'ssr' => 'SSR (Next.js z Node)',
                                ])
                                ->default('static')
                                ->required(),
                        ])
                        ->action(function (Site $record, array $data) {
                            DeploySiteJob::dispatch($record, 'staging', $data['deploy_type']);

                            Notification::make()
                                ->title('Deployment rozpoczęty')
                                ->body("Strona {$record->name} jest wdrażana na staging.")
                                ->success()
                                ->send();
                        }),

                    Tables\Actions\Action::make('deploy_production')
                        ->label('Deploy na Produkcję')
                        ->icon('heroicon-o-rocket-launch')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading('Deploy na Produkcję')
                        ->modalDescription('UWAGA: Strona zostanie opublikowana na produkcji! Upewnij się, że jest przetestowana na staging.')
                        ->modalSubmitActionLabel('Wdróż na produkcję')
                        ->form([
                            Forms\Components\Select::make('deploy_type')
                                ->label('Typ deploymentu')
                                ->options([
                                    'static' => 'Static (HTML/CSS/JS)',
                                    'ssr' => 'SSR (Next.js z Node)',
                                ])
                                ->default('static')
                                ->required(),
                        ])
                        ->action(function (Site $record, array $data) {
                            DeploySiteJob::dispatch($record, 'production', $data['deploy_type']);

                            Notification::make()
                                ->title('Deployment produkcyjny rozpoczęty')
                                ->body("Strona {$record->name} jest wdrażana na produkcję.")
                                ->warning()
                                ->send();
                        }),
                ])
                    ->label('Deploy')
                    ->icon('heroicon-o-server-stack')
                    ->color('primary'),

                Tables\Actions\Action::make('visit_staging')
                    ->label('Staging')
                    ->icon('heroicon-o-eye')
                    ->url(fn ($record) => $record->staging_url)
                    ->openUrlInNewTab()
                    ->visible(fn ($record) => !empty($record->staging_url)),

                Tables\Actions\Action::make('visit_production')
                    ->label('Produkcja')
                    ->icon('heroicon-o-globe-alt')
                    ->color('success')
                    ->url(fn ($record) => $record->production_url)
                    ->openUrlInNewTab()
                    ->visible(fn ($record) => !empty($record->production_url)),

                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\ForceDeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
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
            'index' => Pages\ListSites::route('/'),
            'create' => Pages\CreateSite::route('/create'),
            'view' => Pages\ViewSite::route('/{record}'),
            'edit' => Pages\EditSite::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);

        // Klient widzi tylko swoje strony (przez site_user)
        $user = auth()->user();
        if ($user && $user->hasRole('client')) {
            $siteIds = $user->sites()->pluck('sites.id')->toArray();
            $query->whereIn('id', $siteIds);
        }

        return $query;
    }

    public static function getNavigationBadge(): ?string
    {
        $user = auth()->user();
        
        // Super admin widzi wszystkie
        if ($user && ($user->is_super_admin || $user->hasRole('super_admin'))) {
            return (string) static::getModel()::where('status', 'live')->count() ?: null;
        }
        
        // Klient widzi tylko swoje strony
        if ($user && $user->hasRole('client')) {
            $siteIds = $user->sites()->pluck('sites.id')->toArray();
            $count = static::getModel()::whereIn('id', $siteIds)->count();
            return $count > 0 ? (string) $count : null;
        }

        return null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'success';
    }
}

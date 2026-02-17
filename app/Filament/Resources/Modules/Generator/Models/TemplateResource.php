<?php

declare(strict_types=1);

namespace App\Filament\Resources\Modules\Generator\Models;

use App\Filament\Concerns\HasGlobalBulkActions;
use App\Filament\Concerns\RemembersTableSettings;
use App\Filament\Resources\Modules\Generator\Models\TemplateResource\Pages;
use App\Filament\Resources\SiteResource;
use App\Models\User;
use App\Modules\Generator\Models\Template;
use App\Modules\Generator\Services\TemplateAnalyzerService;
use App\Modules\Generator\Services\TemplateImportService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;

/**
 * Filament Resource dla zarządzania gotowymi szablonami Next.js.
 */
final class TemplateResource extends Resource
{
    use HasGlobalBulkActions;
    use RemembersTableSettings;

    protected static ?string $model = Template::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationLabel = 'Szablony Next.js';

    protected static ?string $modelLabel = 'Szablon';

    protected static ?string $pluralModelLabel = 'Szablony';

    protected static ?string $navigationGroup = 'Generator';

    /**
     * Tylko super admin widzi szablony Next.js.
     */
    public static function shouldRegisterNavigation(): bool
    {
        /** @var User|null $user */
        $user = Auth::user();
        return $user instanceof User && ($user->is_super_admin || $user->hasRole('super_admin'));
    }

    /**
     * Formularz edycji/tworzenia szablonu.
     */
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
                            ->helperText('Unikalny identyfikator szablonu'),

                        Forms\Components\FileUpload::make('template_zip')
                            ->label('Folder szablonu (ZIP)')
                            ->acceptedFileTypes(['application/zip'])
                            ->maxSize(51200) // 50MB
                            ->disk('local')
                            ->directory('templates/uploads')
                            ->visibility('private')
                            ->helperText('Prześlij folder szablonu jako plik ZIP. Po przesłaniu zostanie rozpakowany do templates/ i przeanalizowany przez AI.')
                            ->columnSpanFull()
                            ->required()
                            ->live()
                            ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get) {
                                // Automatycznie wypełnij directory_path na podstawie nazwy pliku ZIP
                                if ($state) {
                                    $zipPath = is_array($state) ? $state[0] : $state;
                                    
                                    if ($zipPath) {
                                        // Obsłuż różne typy: TemporaryUploadedFile lub string
                                        if (is_object($zipPath) && method_exists($zipPath, 'getClientOriginalName')) {
                                            // To jest TemporaryUploadedFile - użyj oryginalnej nazwy
                                            $fileName = $zipPath->getClientOriginalName();
                                        } elseif (is_string($zipPath)) {
                                            // To jest string (ścieżka) - użyj basename
                                            $fileName = basename($zipPath);
                                        } else {
                                            // Nieznany typ - spróbuj jako string
                                            $fileName = (string) $zipPath;
                                            $fileName = basename($fileName);
                                        }
                                        
                                        // Usuń rozszerzenie .zip
                                        $templateName = pathinfo($fileName, PATHINFO_FILENAME);
                                        // Konwertuj na slug
                                        $templateName = \Illuminate\Support\Str::slug($templateName);
                                        
                                        // Wypełnij directory_path tylko jeśli jest puste
                                        if (empty($get('directory_path'))) {
                                            $set('directory_path', $templateName);
                                        }
                                        
                                        // Automatycznie wypełnij name jeśli jest puste
                                        if (empty($get('name'))) {
                                            $set('name', \Illuminate\Support\Str::title(str_replace(['.', '-', '_'], ' ', $templateName)));
                                        }
                                        
                                        // Automatycznie wypełnij slug jeśli jest puste
                                        if (empty($get('slug'))) {
                                            $set('slug', $templateName);
                                        }
                                    }
                                }
                            }),

                        Forms\Components\TextInput::make('directory_path')
                            ->label('Ścieżka do katalogu')
                            ->required(fn (Forms\Get $get) => empty($get('template_zip')))
                            ->maxLength(255)
                            ->helperText('Ścieżka względem templates/ (np. octadecimal.studio). Zostanie wypełniona automatycznie po przesłaniu ZIP.')
                            ->columnSpanFull()
                            ->dehydrated(),

                        Forms\Components\Select::make('category')
                            ->label('Kategoria')
                            ->options([
                                'portfolio' => 'Portfolio',
                                'landing' => 'Landing Page',
                                'corporate' => 'Corporate',
                                'blog' => 'Blog',
                                'ecommerce' => 'E-commerce',
                                'other' => 'Inne',
                            ])
                            ->native(false),

                        Forms\Components\Textarea::make('description')
                            ->label('Opis')
                            ->rows(3)
                            ->columnSpanFull(),

                        Forms\Components\TagsInput::make('tech_stack')
                            ->label('Stack technologiczny')
                            ->separator(',')
                            ->helperText('np. Next.js, TypeScript, Tailwind CSS')
                            ->columnSpanFull(),

                        Forms\Components\TagsInput::make('tags')
                            ->label('Tagi')
                            ->separator(',')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Preview i metadane')
                    ->schema([
                        Forms\Components\TextInput::make('thumbnail_url')
                            ->label('URL miniaturki')
                            ->url()
                            ->maxLength(255)
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('preview_url')
                            ->label('URL preview (iframe)')
                            ->url()
                            ->maxLength(255)
                            ->columnSpanFull(),

                        Forms\Components\KeyValue::make('metadata')
                            ->label('Metadane (JSON)')
                            ->keyLabel('Klucz')
                            ->valueLabel('Wartość')
                            ->columnSpanFull()
                            ->helperText('Komponenty, style, zależności')
                            ->formatStateUsing(function (?array $state): array {
                                if (! $state || ! is_array($state)) {
                                    return [];
                                }
                                
                                // Konwertuj zagnieżdżoną tablicę na płaską strukturę key-value
                                $flattened = [];
                                foreach ($state as $key => $value) {
                                    if (is_array($value)) {
                                        // Dla zagnieżdżonych tablic, użyj JSON
                                        $flattened[$key] = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                                    } else {
                                        $flattened[$key] = (string) $value;
                                    }
                                }
                                
                                return $flattened;
                            })
                            ->dehydrateStateUsing(function (?array $state): array {
                                if (! $state || ! is_array($state)) {
                                    return [];
                                }
                                
                                // Konwertuj z powrotem - próbuj parsować JSON dla wartości
                                $result = [];
                                foreach ($state as $key => $value) {
                                    if (is_string($value) && (str_starts_with($value, '{') || str_starts_with($value, '['))) {
                                        $decoded = json_decode($value, true);
                                        if (json_last_error() === JSON_ERROR_NONE) {
                                            $result[$key] = $decoded;
                                        } else {
                                            $result[$key] = $value;
                                        }
                                    } else {
                                        $result[$key] = $value;
                                    }
                                }
                                
                                return $result;
                            }),
                    ]),

                Forms\Components\Section::make('Status')
                    ->schema([
                        Forms\Components\Toggle::make('is_active')
                            ->label('Aktywny')
                            ->default(true),

                        Forms\Components\Toggle::make('is_premium')
                            ->label('Premium')
                            ->default(false),
                    ])
                    ->columns(2),
            ]);
    }

    /**
     * Tabela listy szablonów.
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
                Tables\Columns\ImageColumn::make('thumbnail_url')
                    ->label('Preview')
                    ->height(60)
                    ->width(60)
                    ->defaultImageUrl('/images/placeholder-template.png')
                    ->getStateUsing(fn (Template $record): ?string => $record->getScreenshotUrl())
                    ->action(
                        Tables\Actions\Action::make('view_thumbnail')
                            ->label('Podgląd miniaturki')
                            ->modalHeading(fn (Template $record): string => "Miniaturka: {$record->name}")
                            ->modalContent(fn (Template $record): \Illuminate\Contracts\Support\Htmlable => new \Illuminate\Support\HtmlString(
                                $record->getScreenshotUrl()
                                    ? '<img src="'.e($record->getScreenshotUrl()).'" alt="'.e($record->name).'" class="w-full rounded-lg shadow-lg" />'
                                    : '<p class="text-gray-500">Brak miniaturki</p>'
                            ))
                            ->modalSubmitAction(false)
                            ->modalCancelActionLabel('Zamknij')
                    )
                    ->extraAttributes(fn (Template $record): array => [
                        'class' => 'cursor-pointer hover:opacity-80 transition-opacity',
                    ]),

                Tables\Columns\TextColumn::make('analysis_status')
                    ->label('Status AI')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'gray',
                        'analyzing' => 'warning',
                        'completed' => 'success',
                        'failed' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(function (string $state, Template $record): string {
                        if ($state === 'analyzing') {
                            return "Analizowanie ({$record->analysis_progress}%)";
                        }
                        return match ($state) {
                            'pending' => 'Oczekuje',
                            'completed' => 'Zakończono',
                            'failed' => 'Błąd',
                            default => $state,
                        };
                    })
                    ->icon(fn (string $state): ?string => match ($state) {
                        'analyzing' => 'heroicon-o-arrow-path',
                        'completed' => 'heroicon-o-check-circle',
                        'failed' => 'heroicon-o-x-circle',
                        default => null,
                    })
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('name')
                    ->label('Nazwa')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->limit(30),

                Tables\Columns\TextColumn::make('category')
                    ->label('Kategoria')
                    ->badge()
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('tech_stack')
                    ->label('Stack')
                    ->formatStateUsing(fn ($state) => is_array($state) ? implode(', ', $state) : (string) ($state ?? ''))
                    ->badge()
                    ->separator(',')
                    ->limit(3)
                    ->toggleable(),

                Tables\Columns\TextColumn::make('directory_path')
                    ->label('Ścieżka')
                    ->searchable()
                    ->limit(30)
                    ->toggleable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Aktywny')
                    ->boolean()
                    ->toggleable(),

                Tables\Columns\IconColumn::make('is_premium')
                    ->label('Premium')
                    ->boolean()
                    ->toggleable(),

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
                    ->options([
                        'portfolio' => 'Portfolio',
                        'landing' => 'Landing Page',
                        'corporate' => 'Corporate',
                        'blog' => 'Blog',
                        'ecommerce' => 'E-commerce',
                        'other' => 'Inne',
                    ])
                    ->native(false),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Aktywny')
                    ->placeholder('Wszystkie')
                    ->trueLabel('Tak')
                    ->falseLabel('Nie'),

                Tables\Filters\TernaryFilter::make('is_premium')
                    ->label('Premium')
                    ->placeholder('Wszystkie')
                    ->trueLabel('Tak')
                    ->falseLabel('Nie'),

                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('preview')
                    ->label('Preview')
                    ->icon('heroicon-o-eye')
                    ->color('info')
                    ->url(fn (Template $record): ?string => $record->getPreviewUrl())
                    ->openUrlInNewTab()
                    ->visible(fn (Template $record): bool => $record->getPreviewUrl() !== null),
                Tables\Actions\Action::make('analyze')
                    ->label('Analizuj HTML')
                    ->icon('heroicon-o-magnifying-glass')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->form([
                        Forms\Components\TextInput::make('html_url')
                            ->label('URL do HTML szablonu')
                            ->placeholder('https://example.com/template.html')
                            ->url()
                            ->required()
                            ->helperText('URL lub ścieżka do pliku HTML do analizy'),
                        Forms\Components\TextInput::make('project_name')
                            ->label('Nazwa projektu')
                            ->default(fn (Template $record): string => $record->name)
                            ->required(),
                    ])
                    ->action(function (Template $record, array $data, TemplateAnalyzerService $analyzerService): void {
                        try {
                            $analysis = $analyzerService->analyzeTemplate(
                                $data['html_url'],
                                $data['project_name']
                            );

                            $blocks = $analyzerService->generateContentBlocks($analysis);

                            Notification::make()
                                ->success()
                                ->title('Analiza zakończona')
                                ->body('Utworzono '.count($blocks).' ContentBlocks z analizy szablonu')
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->danger()
                                ->title('Błąd analizy')
                                ->body($e->getMessage())
                                ->send();
                        }
                    }),
                Tables\Actions\Action::make('deploy')
                    ->label('Wdróż jako stronę')
                    ->icon('heroicon-o-rocket-launch')
                    ->color('success')
                    ->requiresConfirmation()
                    ->form([
                        Forms\Components\Select::make('customer_id')
                            ->label('Klient')
                            ->relationship('customer', 'name', fn ($query) => $query->where('status', 'active'))
                            ->searchable()
                            ->preload()
                            ->required()
                            ->helperText('Wybierz klienta dla którego wdrażasz stronę'),
                        Forms\Components\TextInput::make('subdomain')
                            ->label('Subdomena')
                            ->placeholder('moja-strona')
                            ->required()
                            ->helperText('Subdomena (np. moja-strona dla moja-strona.octadecimal.studio)'),
                        Forms\Components\TextInput::make('domain')
                            ->label('Domena główna')
                            ->default(config('app.domain', 'octadecimal.studio'))
                            ->required()
                            ->helperText('Domena główna (np. octadecimal.studio)'),
                        Forms\Components\TextInput::make('site_name')
                            ->label('Nazwa strony')
                            ->default(fn (Template $record): string => $record->name)
                            ->required(),
                        Forms\Components\Toggle::make('fill_with_example_data')
                            ->label('Wypełnij przykładowymi danymi')
                            ->helperText('Automatycznie utworzy SiteContent z przykładowymi danymi z ContentBlocks')
                            ->default(false),
                        Forms\Components\Toggle::make('publish')
                            ->label('Opublikuj od razu')
                            ->helperText('Ustaw status strony na "live" po wdrożeniu')
                            ->default(false),
                    ])
                    ->action(function (Template $record, array $data): void {
                        try {
                            $deploymentService = app(\App\Modules\Generator\Services\TemplateDeploymentService::class);
                            
                            $result = $deploymentService->deployTemplate($record, [
                                'customer_id' => $data['customer_id'],
                                'subdomain' => $data['subdomain'],
                                'domain' => $data['domain'],
                                'site_name' => $data['site_name'],
                                'fill_with_example_data' => $data['fill_with_example_data'] ?? false,
                                'publish' => $data['publish'] ?? false,
                            ]);

                            Notification::make()
                                ->success()
                                ->title('Wdrożenie rozpoczęte')
                                ->body("Strona {$result['site']->name} została utworzona. Deployment w toku...")
                                ->actions([
                                    \Filament\Notifications\Actions\Action::make('view_site')
                                        ->label('Zobacz stronę')
                                        ->url(SiteResource::getUrl('edit', ['record' => $result['site']])),
                                    \Filament\Notifications\Actions\Action::make('view_deployment')
                                        ->label('Zobacz deployment')
                                        ->url(\App\Filament\Resources\Modules\Deploy\DeploymentResource::getUrl('view', ['record' => $result['deployment']])),
                                ])
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->danger()
                                ->title('Błąd wdrożenia')
                                ->body($e->getMessage())
                                ->send();
                        }
                    })
                    ->visible(fn (Template $record): bool => ! empty($record->directory_path)),
                Tables\Actions\DeleteAction::make(),
                Tables\Actions\RestoreAction::make(),
            ])
            ->headerActions([
                Tables\Actions\Action::make('import')
                    ->label('Importuj szablony')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->form([
                        Forms\Components\Select::make('template_path')
                            ->label('Szablon do importu')
                            ->options(function (): array {
                                $templatesDir = base_path('templates');
                                $options = [];

                                if (! is_dir($templatesDir)) {
                                    return $options;
                                }

                                $directories = array_filter(
                                    glob("{$templatesDir}/*", GLOB_ONLYDIR),
                                    fn (string $path): bool => is_dir($path)
                                );

                                foreach ($directories as $dir) {
                                    $name = basename($dir);
                                    $relativePath = "templates/{$name}";
                                    $options[$name] = $name;
                                }

                                return $options;
                            })
                            ->required()
                            ->searchable(),
                        Forms\Components\TextInput::make('name')
                            ->label('Nazwa (opcjonalnie)')
                            ->helperText('Zostaw puste, aby użyć domyślnej nazwy'),
                        Forms\Components\Select::make('category')
                            ->label('Kategoria')
                            ->options([
                                'portfolio' => 'Portfolio',
                                'landing' => 'Landing Page',
                                'corporate' => 'Corporate',
                                'blog' => 'Blog',
                                'ecommerce' => 'E-commerce',
                                'other' => 'Inne',
                            ])
                            ->native(false),
                    ])
                    ->action(function (array $data, TemplateImportService $importService): void {
                        $user = Auth::user();

                        if (! $user || ! $user->tenant_id) {
                            Notification::make()
                                ->danger()
                                ->title('Błąd')
                                ->body('Użytkownik musi należeć do tenanta')
                                ->send();

                            return;
                        }

                        try {
                            $template = $importService->import(
                                $data['template_path'],
                                $user->tenant_id,
                                [
                                    'name' => $data['name'] ?? null,
                                    'category' => $data['category'] ?? null,
                                ]
                            );

                            Notification::make()
                                ->success()
                                ->title('Sukces')
                                ->body("Szablon '{$template->name}' został zaimportowany")
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->danger()
                                ->title('Błąd importu')
                                ->body($e->getMessage())
                                ->send();
                        }
                    }),
            ])
            ->defaultSort('created_at', 'desc');
        
        // Konfiguruj bulk actions (dodaj jeśli brakuje)
        $table = static::configureBulkActions($table);
        
        return $table;
    }

    /**
     * Infolist dla ViewRecord.
     */
    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Podgląd szablonu')
                    ->schema([
                        Infolists\Components\View::make('filament.resources.templates.preview')
                            ->viewData(fn (Template $record): array => [
                                'previewUrl' => is_string($record->getPreviewUrl()) ? $record->getPreviewUrl() : null,
                                'screenshotUrl' => is_string($record->getScreenshotUrl()) ? $record->getScreenshotUrl() : null,
                            ]),
                    ])
                    ->visible(fn (Template $record): bool => $record->getPreviewUrl() !== null || $record->getScreenshotUrl() !== null)
                    ->columnSpanFull(),

                Infolists\Components\Section::make('Podstawowe informacje')
                    ->schema([
                        Infolists\Components\TextEntry::make('name')
                            ->label('Nazwa'),
                        Infolists\Components\TextEntry::make('slug')
                            ->label('Slug'),
                        Infolists\Components\TextEntry::make('category')
                            ->label('Kategoria')
                            ->badge(),
                        Infolists\Components\TextEntry::make('directory_path')
                            ->label('Ścieżka do katalogu')
                            ->formatStateUsing(fn ($state) => is_string($state) ? $state : (is_array($state) ? implode('/', $state) : (string) ($state ?? ''))),
                        Infolists\Components\TextEntry::make('tech_stack')
                            ->label('Stack technologiczny')
                            ->formatStateUsing(fn ($state) => is_array($state) ? implode(', ', $state) : (string) $state)
                            ->badge()
                            ->separator(','),
                        Infolists\Components\TextEntry::make('description')
                            ->label('Opis')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Infolists\Components\Section::make('Metadane')
                    ->schema([
                        Infolists\Components\TextEntry::make('metadata')
                            ->label('Metadane')
                            ->formatStateUsing(fn ($state) => is_array($state) ? json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : (string) ($state ?? ''))
                            ->columnSpanFull()
                            ->copyable()
                            ->copyMessage('Skopiowano do schowka'),
                    ]),

                Infolists\Components\Section::make('Status')
                    ->schema([
                        Infolists\Components\IconEntry::make('is_active')
                            ->label('Aktywny')
                            ->boolean(),
                        Infolists\Components\IconEntry::make('is_premium')
                            ->label('Premium')
                            ->boolean(),
                        Infolists\Components\TextEntry::make('usage_count')
                            ->label('Liczba użyć'),
                    ])
                    ->columns(3),
            ]);
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
            'index' => Pages\ListTemplates::route('/'),
            'create' => Pages\CreateTemplate::route('/create'),
            'view' => Pages\ViewTemplate::route('/{record}'),
            'edit' => Pages\EditTemplate::route('/{record}/edit'),
        ];
    }

    /**
     * Query builder z domyślnymi filtrami.
     *
     * @return Builder<Template>
     */
    public static function getEloquentQuery(): Builder
    {
        try {
            return parent::getEloquentQuery()
                ->withoutGlobalScopes([
                    SoftDeletingScope::class,
                ]);
        } catch (\Illuminate\Database\QueryException $e) {
            // Jeśli tabela nie istnieje lub baza nie jest dostępna, zwróć pusty query
            if (str_contains($e->getMessage(), "doesn't exist")
                || str_contains($e->getMessage(), 'Base table or view not found')
                || str_contains($e->getMessage(), 'getaddrinfo')
                || str_contains($e->getMessage(), 'Connection refused')) {
                return Template::query()->whereRaw('1 = 0'); // Pusty wynik
            }

            throw $e;
        } catch (\Exception $e) {
            // Dla innych wyjątków, zwróć pusty query
            return Template::query()->whereRaw('1 = 0');
        }
    }

    /**
     * Sprawdź czy Resource może być wyświetlony.
     */
    public static function canViewAny(): bool
    {
        try {
            // Sprawdź czy baza jest dostępna i tabela istnieje
            if (! \Illuminate\Support\Facades\DB::connection()->getPdo()) {
                return false;
            }

            return \Illuminate\Support\Facades\Schema::hasTable('templates') && parent::canViewAny();
        } catch (\Exception $e) {
            // Jeśli baza nie jest dostępna lub tabela nie istnieje, ukryj Resource
            return false;
        }
    }
}

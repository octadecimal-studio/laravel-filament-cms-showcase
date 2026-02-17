<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Modules\Content\Models\ContentTemplate;
use App\Modules\Generator\Models\GeneratedTemplate;
use App\Modules\Generator\Models\PromptTemplate;
use App\Modules\Generator\Services\ComponentGeneratorService;
use App\Modules\Generator\Services\TemplateGeneratorService;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

/**
 * Strona generatora szablonów AI.
 */
class AITemplateGenerator extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-sparkles';

    protected static string $view = 'filament.pages.ai-template-generator';

    protected static ?string $slug = 'ai-template-generator';

    protected static ?string $navigationLabel = 'Generator AI';

    protected static ?string $title = 'Generator szablonów AI';

    protected static ?string $navigationGroup = 'Generator';

    protected static ?int $navigationSort = 1;

    /**
     * Tylko super admin widzi generator AI.
     */
    public static function shouldRegisterNavigation(): bool
    {
        /** @var \App\Models\User|null $user */
        $user = \Illuminate\Support\Facades\Auth::user();
        return $user instanceof \App\Models\User && ($user->is_super_admin || $user->hasRole('super_admin'));
    }

    /**
     * Dane formularza.
     *
     * @var array<string, mixed>
     */
    public array $data = [];

    /**
     * Wygenerowany szablon.
     */
    public ?GeneratedTemplate $generatedTemplate = null;

    /**
     * Status generowania.
     */
    public string $generationStatus = 'idle'; // idle, generating, completed, failed

    /**
     * Błąd generowania.
     */
    public ?string $generationError = null;

    /**
     * Inicjalizacja strony.
     */
    public function mount(): void
    {
        $this->form->fill([
            'ai_provider' => 'claude',
            'model' => 'claude-sonnet-4-20250514',
            'openai_model' => 'gpt-4-turbo-preview',
            'prompt' => '',
            'prompt_template_id' => null,
            'image' => null,
            'variables' => [
                'colors' => [],
                'fonts' => [],
                'texts' => [],
            ],
        ]);
    }

    /**
     * Formularz generatora.
     */
    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Parametry generowania')
                    ->schema([
                        Forms\Components\Textarea::make('prompt')
                            ->label('Prompt')
                            ->placeholder('Opisz szablon, który chcesz wygenerować...')
                            ->required()
                            ->rows(4)
                            ->helperText('Opisz szczegółowo, jaki szablon chcesz wygenerować (np. "Nowoczesna sekcja hero z animacjami i przyciskiem CTA")')
                            ->columnSpanFull(),

                        Forms\Components\Select::make('prompt_template_id')
                            ->label('Szablon promptu (opcjonalny)')
                            ->options(function () {
                                try {
                                    return PromptTemplate::query()
                                        ->where('is_active', true)
                                        ->get()
                                        ->mapWithKeys(fn (PromptTemplate $template) => [
                                            $template->id => $template->name.' ('.$template->category.')',
                                        ])
                                        ->toArray();
                                } catch (\Exception $e) {
                                    // Tabela nie istnieje lub baza nie jest dostępna
                                    return [];
                                }
                            })
                            ->searchable()
                            ->preload()
                            ->helperText('Wybierz gotowy szablon promptu dla lepszych wyników')
                            ->visible(fn () => \Illuminate\Support\Facades\Schema::hasTable('prompt_templates')),

                        Forms\Components\FileUpload::make('image')
                            ->label('Obrazek referencyjny (opcjonalny)')
                            ->image()
                            ->imageEditor()
                            ->maxSize(5120) // 5MB
                            ->helperText('Dodaj obrazek jako referencję wizualną (Vision API)')
                            ->columnSpanFull(),

                        Forms\Components\Select::make('ai_provider')
                            ->label('Dostawca AI')
                            ->options([
                                'claude' => 'Claude (Anthropic)',
                                'openai' => 'OpenAI (GPT-4)',
                            ])
                            ->default('claude')
                            ->required()
                            ->native(false),

                        Forms\Components\Select::make('model')
                            ->label('Model')
                            ->options([
                                'claude-sonnet-4-20250514' => 'Claude Sonnet 4',
                                'claude-opus-3-20240229' => 'Claude Opus 3',
                                'gpt-4-turbo-preview' => 'GPT-4 Turbo',
                                'gpt-4' => 'GPT-4',
                            ])
                            ->default('claude-sonnet-4-20250514')
                            ->required()
                            ->native(false)
                            ->visible(fn (Forms\Get $get) => $get('ai_provider') === 'claude')
                            ->reactive(),

                        Forms\Components\Select::make('openai_model')
                            ->label('Model')
                            ->options([
                                'gpt-4-turbo-preview' => 'GPT-4 Turbo',
                                'gpt-4' => 'GPT-4',
                            ])
                            ->default('gpt-4-turbo-preview')
                            ->required()
                            ->native(false)
                            ->visible(fn (Forms\Get $get) => $get('ai_provider') === 'openai')
                            ->reactive(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Zmienne (opcjonalne)')
                    ->schema([
                        Forms\Components\KeyValue::make('variables.colors')
                            ->label('Kolory')
                            ->keyLabel('Nazwa')
                            ->valueLabel('Wartość (hex)')
                            ->helperText('Np. primary: #3b82f6'),

                        Forms\Components\KeyValue::make('variables.fonts')
                            ->label('Fonty')
                            ->keyLabel('Nazwa')
                            ->valueLabel('Nazwa fontu')
                            ->helperText('Np. heading: Inter'),

                        Forms\Components\KeyValue::make('variables.texts')
                            ->label('Teksty')
                            ->keyLabel('Nazwa')
                            ->valueLabel('Tekst')
                            ->helperText('Np. title: Witaj w naszym serwisie'),
                    ])
                    ->collapsible()
                    ->collapsed(),
            ])
            ->statePath('data');
    }

    /**
     * Akcje w nagłówku strony.
     */
    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('generate')
                ->label('Wygeneruj szablon')
                ->icon('heroicon-o-sparkles')
                ->color('primary')
                ->size('lg')
                ->action('generate')
                ->disabled(fn () => $this->generationStatus === 'generating'),
            \Filament\Actions\Action::make('reset')
                ->label('Resetuj')
                ->color('gray')
                ->action('resetForm')
                ->disabled(fn () => $this->generationStatus === 'generating'),
        ];
    }

    /**
     * Generuj szablon.
     */
    public function generate(): void
    {
        try {
            $data = $this->form->getState();
        } catch (\Illuminate\Validation\ValidationException $e) {
            Notification::make()
                ->title('Błąd walidacji')
                ->body($e->getMessage())
                ->danger()
                ->send();

            return;
        }

        $this->generationStatus = 'generating';
        $this->generationError = null;
        $this->generatedTemplate = null;

        try {
            $generatorService = app(TemplateGeneratorService::class);

            // Pobierz URL obrazka jeśli został przesłany
            $imageUrl = null;
            if (isset($data['image']) && is_array($data['image']) && ! empty($data['image'])) {
                $imagePath = storage_path('app/public/'.($data['image'][0] ?? ''));
                if (file_exists($imagePath)) {
                    $imageUrl = asset('storage/'.($data['image'][0] ?? ''));
                }
            }

            // Pobierz prompt template jeśli wybrany
            $promptTemplate = null;
            if (isset($data['prompt_template_id'])) {
                $promptTemplate = PromptTemplate::find($data['prompt_template_id']);
            }

            // Wybierz model
            $model = $data['ai_provider'] === 'openai'
                ? ($data['openai_model'] ?? 'gpt-4-turbo-preview')
                : ($data['model'] ?? 'claude-sonnet-4-20250514');

            // Generuj szablon
            if ($data['ai_provider'] === 'openai') {
                $this->generatedTemplate = $generatorService->generateWithOpenAI(
                    $data['prompt'],
                    $imageUrl,
                    $promptTemplate,
                    $model
                );
            } else {
                $this->generatedTemplate = $generatorService->generateWithClaude(
                    $data['prompt'],
                    $imageUrl,
                    $promptTemplate,
                    $model
                );
            }

            $this->generationStatus = 'completed';

            Notification::make()
                ->title('Szablon wygenerowany pomyślnie')
                ->success()
                ->send();
        } catch (\Exception $e) {
            $this->generationStatus = 'failed';
            $this->generationError = $e->getMessage();

            Notification::make()
                ->title('Błąd generowania')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    /**
     * Zapisz jako Template Next.js.
     */
    public function saveAsNextJsTemplate(): void
    {
        if (! $this->generatedTemplate || $this->generationStatus !== 'completed') {
            Notification::make()
                ->title('Brak wygenerowanego szablonu')
                ->warning()
                ->send();

            return;
        }

        try {
            $service = app(\App\Modules\Generator\Services\TemplateFromGeneratedService::class);
            $generatedCode = $this->generatedTemplate->generated_code ?? [];
            
            // Wyciągnij nazwę z wygenerowanego kodu lub promptu
            $name = $generatedCode['metadata']['name'] ?? null;
            if (! $name) {
                // Fallback: użyj pierwszych słów z promptu
                $prompt = $this->generatedTemplate->prompt;
                $words = explode(' ', trim($prompt));
                $name = implode(' ', array_slice($words, 0, 5));
                $name = \Illuminate\Support\Str::limit($name, 50);
            }
            
            $slug = \Illuminate\Support\Str::slug($name);

            $template = $service->createTemplateFromGenerated(
                $this->generatedTemplate,
                $name,
                $slug
            );

            Notification::make()
                ->title('Szablon zapisany')
                ->body("Szablon został zapisany jako Template Next.js: {$template->name}")
                ->success()
                ->actions([
                    \Filament\Notifications\Actions\Action::make('view')
                        ->label('Zobacz szablon')
                        ->url(\App\Filament\Resources\Modules\Generator\Models\TemplateResource::getUrl('edit', ['record' => $template])),
                    \Filament\Notifications\Actions\Action::make('view_all')
                        ->label('Zobacz wszystkie wygenerowane')
                        ->url(\App\Filament\Resources\Modules\Generator\Models\GeneratedTemplateResource::getUrl('index')),
                ])
                ->send();
        } catch (\Exception $e) {
            Notification::make()
                ->title('Błąd zapisu')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    /**
     * Zapisz jako ContentTemplate.
     */
    public function saveAsContentTemplate(): void
    {
        if (! $this->generatedTemplate || $this->generationStatus !== 'completed') {
            Notification::make()
                ->title('Brak wygenerowanego szablonu')
                ->warning()
                ->send();

            return;
        }

        try {
            $componentService = app(ComponentGeneratorService::class);
            $variables = $this->data['variables'] ?? [];

            $contentTemplate = $componentService->generateComponent(
                $this->generatedTemplate,
                $variables
            );

            Notification::make()
                ->title('Szablon zapisany')
                ->body('Szablon został zapisany jako ContentTemplate')
                ->success()
                ->actions([
                    \Filament\Notifications\Actions\Action::make('view')
                        ->label('Zobacz')
                        ->url(\App\Filament\Resources\Modules\Content\Models\ContentTemplateResource::getUrl('edit', ['record' => $contentTemplate])),
                ])
                ->send();
        } catch (\Exception $e) {
            Notification::make()
                ->title('Błąd zapisu')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    /**
     * Resetuj formularz.
     */
    public function resetForm(): void
    {
        $this->form->fill([
            'ai_provider' => 'claude',
            'model' => 'claude-sonnet-4-20250514',
            'openai_model' => 'gpt-4-turbo-preview',
            'prompt' => '',
            'prompt_template_id' => null,
            'image' => null,
            'variables' => [
                'colors' => [],
                'fonts' => [],
                'texts' => [],
            ],
        ]);
        $this->generatedTemplate = null;
        $this->generationStatus = 'idle';
        $this->generationError = null;
    }

    /**
     * Pobierz status generowania.
     */
    public function getGenerationStatusProperty(): string
    {
        return $this->generationStatus;
    }

    /**
     * Pobierz wygenerowany kod.
     */
    public function getGeneratedCodeProperty(): ?array
    {
        return $this->generatedTemplate?->generated_code;
    }
}

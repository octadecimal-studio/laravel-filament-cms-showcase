<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Models\User;
use App\Models\UserCustomNavigationItem;
use Filament\Forms;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class EditProfile extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-user-circle';

    protected static string $view = 'filament.pages.edit-profile';

    protected static ?string $navigationLabel = 'Mój profil';

    protected static ?string $title = 'Edytuj profil';

    protected static ?int $navigationSort = 999;

    protected static bool $shouldRegisterNavigation = true;

    protected static ?string $navigationGroup = null;

    /**
     * Sprawdza czy strona powinna być widoczna w nawigacji.
     */
    public static function shouldRegisterNavigation(): bool
    {
        return true; // Zawsze widoczna dla zalogowanych użytkowników
    }

    public ?array $data = [];

    public function mount(): void
    {
        /** @var User $user */
        $user = Auth::user();
        
        $this->form->fill([
            'name' => $user->name,
            'email' => $user->email,
            'avatar_url' => $user->avatar_url,
            'wallpaper_url' => $user->wallpaper_url,
            'panel_preferences' => $user->panel_preferences ?? [
                'sidebar_width' => 'normal',
            ],
            'custom_navigation_items' => $user->customNavigationItems->map(function ($item) {
                return [
                    'id' => $item->id,
                    'label' => $item->label,
                    'icon' => $item->icon,
                    'url' => $item->url,
                    'group' => $item->group,
                    'sort_order' => $item->sort_order,
                    'is_pinned_to_topbar' => $item->is_pinned_to_topbar,
                    'is_active' => $item->is_active,
                    'open_in_new_tab' => $item->open_in_new_tab,
                ];
            })->toArray(),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Dane personalne')
                    ->schema([
                        TextInput::make('name')
                            ->label('Imię i nazwisko')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('email')
                            ->label('Email')
                            ->email()
                            ->required()
                            ->maxLength(255),

                        FileUpload::make('avatar_url')
                            ->label('Zdjęcie profilowe')
                            ->image()
                            ->directory('avatars')
                            ->disk('public')
                            ->visibility('public')
                            ->maxSize(2048)
                            ->imageEditor()
                            ->imageEditorAspectRatios([
                                '1:1',
                            ])
                            ->helperText('Maksymalny rozmiar: 2MB'),
                    ])
                    ->columns(2),

                Section::make('Personalizacja panelu')
                    ->schema([
                        FileUpload::make('wallpaper_url')
                            ->label('Tapeta')
                            ->image()
                            ->directory('wallpapers')
                            ->disk('public')
                            ->visibility('public')
                            ->maxSize(5120)
                            ->helperText('Maksymalny rozmiar: 5MB'),

                        TextInput::make('panel_preferences.sidebar_width')
                            ->label('Szerokość sidebaru')
                            ->default('normal')
                            ->helperText('normal, wide, narrow'),
                    ])
                    ->columns(2),

                Section::make('Niestandardowe linki w menu')
                    ->description('Dodaj własne linki do menu po lewej stronie')
                    ->schema([
                        Repeater::make('custom_navigation_items')
                            ->label('Linki')
                            ->schema([
                                TextInput::make('label')
                                    ->label('Nazwa')
                                    ->required()
                                    ->maxLength(255),

                                TextInput::make('icon')
                                    ->label('Ikona (Heroicon)')
                                    ->placeholder('heroicon-o-home')
                                    ->helperText('Nazwa ikony z Heroicons (np. heroicon-o-home)')
                                    ->maxLength(255),

                                TextInput::make('url')
                                    ->label('URL')
                                    ->url()
                                    ->required()
                                    ->maxLength(255),

                                TextInput::make('group')
                                    ->label('Grupa')
                                    ->maxLength(255)
                                    ->helperText('Opcjonalna grupa menu'),

                                TextInput::make('sort_order')
                                    ->label('Kolejność')
                                    ->numeric()
                                    ->default(0)
                                    ->helperText('Niższa liczba = wyżej w menu'),

                                Toggle::make('is_pinned_to_topbar')
                                    ->label('Przypnij do górnego menu')
                                    ->helperText('Pokaż jako zakładka w górnym menu'),

                                Toggle::make('is_active')
                                    ->label('Aktywny')
                                    ->default(true),

                                Toggle::make('open_in_new_tab')
                                    ->label('Otwórz w nowej karcie'),
                            ])
                            ->columns(2)
                            ->defaultItems(0)
                            ->collapsible()
                            ->itemLabel(fn (array $state): ?string => $state['label'] ?? null)
                            ->reorderable()
                            ->addActionLabel('Dodaj link'),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        /** @var User $user */
        $user = Auth::user();
        $data = $this->form->getState();

        // Aktualizuj dane użytkownika
        $user->update([
            'name' => $data['name'],
            'email' => $data['email'],
            'avatar_url' => $data['avatar_url'] ?? null,
            'wallpaper_url' => $data['wallpaper_url'] ?? null,
            'panel_preferences' => $data['panel_preferences'] ?? null,
        ]);

        // Synchronizuj custom navigation items
        if (isset($data['custom_navigation_items'])) {
            // Usuń stare
            $user->customNavigationItems()->delete();

            // Dodaj nowe
            foreach ($data['custom_navigation_items'] as $index => $item) {
                UserCustomNavigationItem::create([
                    'user_id' => $user->id,
                    'label' => $item['label'],
                    'icon' => $item['icon'] ?? null,
                    'url' => $item['url'],
                    'group' => $item['group'] ?? null,
                    'sort_order' => $item['sort_order'] ?? $index,
                    'is_pinned_to_topbar' => $item['is_pinned_to_topbar'] ?? false,
                    'is_active' => $item['is_active'] ?? true,
                    'open_in_new_tab' => $item['open_in_new_tab'] ?? false,
                ]);
            }
        }

        Notification::make()
            ->title('Profil zaktualizowany')
            ->body('Twoje ustawienia zostały zapisane. Odśwież stronę aby zobaczyć zmiany.')
            ->success()
            ->send();

        // Odśwież stronę aby zastosować zmiany
        $this->redirect(static::getUrl(), navigate: true);
    }

    protected function getFormActions(): array
    {
        return [
            \Filament\Forms\Components\Actions\Action::make('save')
                ->label('Zapisz zmiany')
                ->submit('save')
                ->color('primary'),
        ];
    }

    public function getCachedFormActions(): array
    {
        return $this->getFormActions();
    }

    public function hasFullWidthFormActions(): bool
    {
        return false;
    }
}

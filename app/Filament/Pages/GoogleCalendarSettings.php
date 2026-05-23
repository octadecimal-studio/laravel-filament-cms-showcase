<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Models\GoogleCalendarSetting;
use App\Services\GoogleCalendarService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

class GoogleCalendarSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-calendar-days';

    protected string $view = 'filament.pages.google-calendar-settings';

    protected static ?string $navigationLabel = 'Google Calendar';

    protected static ?string $title = 'Synchronizacja Google Calendar';

    protected static string|\UnitEnum|null $navigationGroup = 'Ustawienia';

    protected static ?int $navigationSort = 60;

    public ?array $data = [];

    public function mount(): void
    {
        $settings = GoogleCalendarSetting::instance();

        $this->form->fill([
            'client_id'   => $settings->client_id,
            'calendar_id' => $settings->calendar_id,
            // client_secret celowo nie jest wypełniany — pole password nie ujawnia wartości
        ]);
    }

    public function form(Schema $schema): Schema
    {
        $service = app(GoogleCalendarService::class);
        $hasToken = $service->hasToken();
        $isConnected = $service->isConnected();
        $hasCredentials = $service->hasCredentials();
        $calendarOptions = $hasToken ? $service->listCalendars() : [];

        return $schema
            ->statePath('data')
            ->components([
                Section::make('Dane logowania Google Cloud')
                    ->description(new HtmlString(
                        'Utwórz projekt w <a href="https://console.cloud.google.com/" target="_blank" class="underline">Google Cloud Console</a>, '
                        . 'włącz <strong>Google Calendar API</strong>, przejdź do <em>APIs &amp; Services → Credentials → Create Credentials → OAuth 2.0 Client IDs</em> '
                        . '(typ: <em>Aplikacja internetowa</em>). Jako <strong>Autoryzowany URI przekierowania</strong> podaj:<br>'
                        . '<code class="text-xs bg-gray-100 dark:bg-gray-800 px-1 rounded">'
                        . route('google-calendar.callback') . '</code>'
                    ))
                    ->schema([
                        TextInput::make('client_id')
                            ->label('Client ID')
                            ->placeholder('XXXXXXXXXX.apps.googleusercontent.com')
                            ->required(),

                        TextInput::make('client_secret')
                            ->label('Client Secret')
                            ->password()
                            ->revealable()
                            ->placeholder($hasCredentials ? '(pozostaw puste, by nie zmieniać)' : 'GOCSPX-...')
                            ->helperText($hasCredentials ? 'Wpisz tylko jeśli chcesz zmienić Client Secret.' : null),
                    ]),

                Section::make('Połączenie i kalendarz')
                    ->schema([
                        Select::make('calendar_id')
                            ->label('Kalendarz docelowy')
                            ->options($calendarOptions)
                            ->placeholder($hasToken ? 'Wybierz kalendarz' : 'Najpierw połącz konto Google')
                            ->disabled(! $hasToken)
                            ->helperText($hasToken
                                ? 'Rezerwacje będą synchronizowane do wybranego kalendarza.'
                                : 'Kliknij "Połącz z Google Calendar" po zapisaniu credentials.'),

                        Actions::make([
                            Action::make('save')
                                ->label('Zapisz')
                                ->action('saveSettings')
                                ->color('primary'),

                            Action::make('sync_all')
                                ->label('Synchronizuj z Google Calendar')
                                ->icon('heroicon-o-arrow-path')
                                ->color('info')
                                ->requiresConfirmation()
                                ->modalHeading('Synchronizacja rezerwacji')
                                ->modalDescription('Przesłane zostaną wszystkie aktywne rezerwacje (pending, confirmed, paid), które nie mają jeszcze wpisu w kalendarzu. Anulowane i wygasłe zostaną pominięte.')
                                ->modalSubmitActionLabel('Synchronizuj')
                                ->action('syncAllRentals')
                                ->visible($hasToken),

                            Action::make('connect')
                                ->label('Połącz z Google Calendar')
                                ->url(route('google-calendar.redirect'))
                                ->icon('heroicon-o-arrow-top-right-on-square')
                                ->color('success')
                                ->visible($hasCredentials && ! $hasToken),

                            Action::make('reconnect')
                                ->label('Połącz ponownie')
                                ->url(route('google-calendar.redirect'))
                                ->icon('heroicon-o-arrow-path')
                                ->color('gray')
                                ->visible($hasToken && ! $isConnected),

                            Action::make('disconnect')
                                ->label('Rozłącz')
                                ->action('disconnectGoogle')
                                ->color('danger')
                                ->requiresConfirmation()
                                ->visible($hasToken),
                        ]),
                    ]),
            ]);
    }

    public function saveSettings(): void
    {
        $data = $this->form->getState();
        $settings = GoogleCalendarSetting::instance();

        $updateData = ['calendar_id' => $data['calendar_id'] ?? null];

        if (! empty($data['client_id'])) {
            $updateData['client_id'] = $data['client_id'];
        }

        if (! empty($data['client_secret'])) {
            $updateData['client_secret'] = $data['client_secret'];
        }

        $settings->update($updateData);

        Notification::make()->title('Zapisano')->success()->send();
    }

    public function syncAllRentals(): void
    {
        $service = app(GoogleCalendarService::class);

        if (! $service->isConnected()) {
            Notification::make()
                ->title('Wybierz i zapisz kalendarz docelowy przed synchronizacją')
                ->warning()
                ->send();

            return;
        }

        $result = $service->syncAllRentals();

        Notification::make()
            ->title('Synchronizacja zakończona')
            ->body("Dodano: {$result['synced']} | Pominięto (już w kalendarzu): {$result['skipped']} | Błędy: {$result['failed']}")
            ->success()
            ->send();
    }

    public function disconnectGoogle(): void
    {
        app(GoogleCalendarService::class)->disconnect();

        Notification::make()->title('Rozłączono od Google Calendar')->success()->send();

        $this->redirect(static::getUrl());
    }
}

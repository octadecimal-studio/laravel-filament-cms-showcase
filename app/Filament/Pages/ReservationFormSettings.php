<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Modules\Content\Models\TwoWheels\SiteSetting;
use App\Modules\Core\Models\Tenant;
use App\Modules\Core\Scopes\TenantScope;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class ReservationFormSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-document-check';

    protected static string $view = 'filament.pages.reservation-form-settings';

    protected static ?string $navigationLabel = 'Formularz rezerwacji';

    protected static ?string $title = 'Ustawienia formularza rezerwacji';

    protected static ?int $navigationSort = 75;

    public ?array $data = [];

    public function mount(): void
    {
        $setting = $this->getSiteSetting();

        $this->form->fill([
            'reservation_form_type' => $setting?->reservation_form_type ?? 'internal',
            'reservation_form_external_url' => $setting?->reservation_form_external_url,
            'reservation_notification_email' => $setting?->reservation_notification_email,
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Typ formularza')
                    ->schema([
                        Forms\Components\Radio::make('reservation_form_type')
                            ->label('Typ formularza rezerwacji')
                            ->options([
                                'internal' => 'Wewnętrzny (formularz na stronie)',
                                'external' => 'Zewnętrzny (link do zewnętrznego formularza)',
                            ])
                            ->default('internal')
                            ->required()
                            ->live(),

                        Forms\Components\TextInput::make('reservation_form_external_url')
                            ->label('URL zewnętrznego formularza')
                            ->url()
                            ->maxLength(500)
                            ->placeholder('https://example.com/reservation-form')
                            ->visible(fn (Forms\Get $get): bool => $get('reservation_form_type') === 'external'),
                    ]),

                Forms\Components\Section::make('Powiadomienia email')
                    ->description('Adres email, na który będą wysyłane powiadomienia o nowych rezerwacjach.')
                    ->schema([
                        Forms\Components\TextInput::make('reservation_notification_email')
                            ->label('Email do powiadomień')
                            ->email()
                            ->maxLength(255)
                            ->placeholder('rezerwacje@example-rental.test')
                            ->helperText('Zostaw puste, aby wyłączyć powiadomienia email.'),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $setting = $this->getSiteSetting();

        if (! $setting) {
            Notification::make()
                ->title('Błąd')
                ->body('Nie znaleziono ustawień strony. Sprawdź konfigurację tenanta.')
                ->danger()
                ->send();

            return;
        }

        $setting->update([
            'reservation_form_type' => $data['reservation_form_type'],
            'reservation_form_external_url' => $data['reservation_form_external_url'],
            'reservation_notification_email' => $data['reservation_notification_email'],
        ]);

        Notification::make()
            ->title('Zapisano')
            ->body('Ustawienia formularza rezerwacji zostały zaktualizowane.')
            ->success()
            ->send();
    }

    private function getSiteSetting(): ?SiteSetting
    {
        $user = auth()->user();
        $tenantId = $user?->tenant_id
            ?? Tenant::where('slug', 'demo-studio')->where('is_active', true)->value('id')
            ?? Tenant::where('is_active', true)->value('id');

        return SiteSetting::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $tenantId)
            ->first();
    }
}

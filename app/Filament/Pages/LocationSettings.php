<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use App\Modules\Content\Models\TwoWheels\SiteSetting;
use App\Modules\Core\Models\Tenant;
use App\Modules\Core\Scopes\TenantScope;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class LocationSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-identification';

    protected string $view = 'filament.pages.location-settings';

    protected static ?string $navigationLabel = 'Dane kontaktowe';

    protected static ?string $title = 'Dane kontaktowe';

    protected static ?int $navigationSort = 65;

    public ?array $data = [];

    public function mount(): void
    {
        $setting = $this->getSiteSetting();
        $companyData = $setting?->company_data ?? [];

        $this->form->fill([
            'location_title' => $setting?->location_title ?? 'Lokalizacja',
            'location_description' => $setting?->location_description,
            'address' => $setting?->address,
            'map_coordinates' => $setting?->map_coordinates,
            'contact_phone' => $setting?->contact_phone,
            'contact_email' => $setting?->contact_email,
            'opening_hours' => $setting?->opening_hours,
            'pickup_hours' => $setting?->pickup_hours ?? ['09:00', '10:00', '11:00', '12:00', '13:00', '14:00', '15:00', '16:00', '17:00'],
            'company_name' => $companyData['company_name'] ?? null,
            'nip' => $companyData['nip'] ?? null,
            'krs' => $companyData['krs'] ?? null,
            'regon' => $companyData['regon'] ?? null,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Nagłówek sekcji lokalizacji')
                    ->schema([
                        TextInput::make('location_title')
                            ->label('Tytuł sekcji')
                            ->required()
                            ->maxLength(255),

                        Textarea::make('location_description')
                            ->label('Opis sekcji')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Section::make('Adres')
                    ->schema([
                        TextInput::make('company_name')
                            ->label('Nazwa firmy')
                            ->maxLength(255)
                            ->columnSpanFull(),

                        Textarea::make('address')
                            ->label('Adres')
                            ->rows(2)
                            ->required()
                            ->columnSpanFull(),

                        TextInput::make('nip')
                            ->label('NIP')
                            ->maxLength(20),

                        TextInput::make('krs')
                            ->label('KRS')
                            ->maxLength(20),

                        TextInput::make('regon')
                            ->label('REGON')
                            ->maxLength(20),
                    ])
                    ->columns(3),

                Section::make('Kontakt')
                    ->schema([
                        TextInput::make('contact_phone')
                            ->label('Telefon')
                            ->tel()
                            ->maxLength(20)
                            ->required(),

                        TextInput::make('contact_email')
                            ->label('Email')
                            ->email()
                            ->maxLength(255)
                            ->required(),
                    ])
                    ->columns(2),

                Section::make('Godziny otwarcia')
                    ->schema([
                        Textarea::make('opening_hours')
                            ->label('Godziny otwarcia')
                            ->rows(3)
                            ->required()
                            ->columnSpanFull(),
                    ]),

                Section::make('Godziny odbioru i zwrotu motocykla (KML-0047)')
                    ->description('Lista godzin (HH:MM), z których klient wybiera porę odbioru i zwrotu motocykla. Każda godzina po dobie startu rozpoczyna kolejną dobę.')
                    ->schema([
                        TagsInput::make('pickup_hours')
                            ->label('Dostępne godziny odbioru/zwrotu')
                            ->placeholder('np. 10:00')
                            ->helperText('Format HH:MM (np. 09:00, 10:30). Naciśnij Enter po każdej wartości.')
                            ->columnSpanFull(),
                    ]),

                Section::make('Mapa')
                    ->schema([
                        TextInput::make('map_coordinates')
                            ->label('Współrzędne mapy')
                            ->placeholder('52.2297,21.0122')
                            ->helperText('Format: szerokość,długość (np. 52.2297,21.0122)')
                            ->required()
                            ->columnSpanFull(),
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
            'location_title' => $data['location_title'],
            'location_description' => $data['location_description'],
            'address' => $data['address'],
            'map_coordinates' => $data['map_coordinates'],
            'contact_phone' => $data['contact_phone'],
            'contact_email' => $data['contact_email'],
            'opening_hours' => $data['opening_hours'],
            'pickup_hours' => $data['pickup_hours'] ?? null,
            'company_data' => array_merge($setting->company_data ?? [], [
                'company_name' => $data['company_name'] ?? null,
                'nip' => $data['nip'] ?? null,
                'krs' => $data['krs'] ?? null,
                'regon' => $data['regon'] ?? null,
            ]),
        ]);

        Notification::make()
            ->title('Zapisano')
            ->body('Ustawienia lokalizacji zostały zaktualizowane.')
            ->success()
            ->send();
    }

    private function getSiteSetting(): ?SiteSetting
    {
        $user = auth()->user();
        $tenantId = $user?->tenant_id;

        // Super admin (system tenant) — fallback to first real tenant with settings
        if (! $tenantId || $tenantId === '00000000-0000-0000-0000-000000000000') {
            $tenantId = Tenant::where('slug', 'demo-studio')->where('is_active', true)->value('id')
                ?? Tenant::where('is_active', true)->where('id', '!=', '00000000-0000-0000-0000-000000000000')->value('id');
        }

        return SiteSetting::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $tenantId)
            ->first();
    }
}

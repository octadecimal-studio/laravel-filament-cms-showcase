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

class LocationSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-map-pin';

    protected static string $view = 'filament.pages.location-settings';

    protected static ?string $navigationLabel = 'Lokalizacja';

    protected static ?string $title = 'Ustawienia lokalizacji';

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
            'company_name' => $companyData['company_name'] ?? null,
            'nip' => $companyData['nip'] ?? null,
            'krs' => $companyData['krs'] ?? null,
            'regon' => $companyData['regon'] ?? null,
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Nagłówek sekcji lokalizacji')
                    ->schema([
                        Forms\Components\TextInput::make('location_title')
                            ->label('Tytuł sekcji')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\Textarea::make('location_description')
                            ->label('Opis sekcji')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Dane kontaktowe')
                    ->schema([
                        Forms\Components\TextInput::make('contact_phone')
                            ->label('Telefon')
                            ->tel()
                            ->maxLength(20)
                            ->required(),

                        Forms\Components\TextInput::make('contact_email')
                            ->label('Email')
                            ->email()
                            ->maxLength(255)
                            ->required(),

                        Forms\Components\Textarea::make('address')
                            ->label('Adres')
                            ->rows(2)
                            ->required()
                            ->columnSpanFull(),

                        Forms\Components\Textarea::make('opening_hours')
                            ->label('Godziny otwarcia')
                            ->rows(2)
                            ->required()
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('map_coordinates')
                            ->label('Współrzędne mapy')
                            ->placeholder('52.2297,21.0122')
                            ->helperText('Format: szerokość,długość (np. 52.2297,21.0122)')
                            ->required()
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Dane firmy')
                    ->description('Dane rejestrowe firmy wyświetlane w stopce i dokumentach.')
                    ->schema([
                        Forms\Components\TextInput::make('company_name')
                            ->label('Nazwa firmy')
                            ->maxLength(255)
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('nip')
                            ->label('NIP')
                            ->maxLength(20),

                        Forms\Components\TextInput::make('krs')
                            ->label('KRS')
                            ->maxLength(20),

                        Forms\Components\TextInput::make('regon')
                            ->label('REGON')
                            ->maxLength(20),
                    ])
                    ->columns(3),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $setting = $this->getSiteSetting();

        if ($setting) {
            $setting->update([
                'location_title' => $data['location_title'],
                'location_description' => $data['location_description'],
                'address' => $data['address'],
                'map_coordinates' => $data['map_coordinates'],
                'contact_phone' => $data['contact_phone'],
                'contact_email' => $data['contact_email'],
                'opening_hours' => $data['opening_hours'],
                'company_data' => [
                    'company_name' => $data['company_name'] ?? null,
                    'nip' => $data['nip'] ?? null,
                    'krs' => $data['krs'] ?? null,
                    'regon' => $data['regon'] ?? null,
                ],
            ]);
        }

        Notification::make()
            ->title('Zapisano')
            ->body('Ustawienia lokalizacji zostały zaktualizowane.')
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

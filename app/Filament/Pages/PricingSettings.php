<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Placeholder;
use Filament\Schemas\Components\Actions;
use Filament\Actions\Action;
use App\Modules\Content\Models\TwoWheels\Motorcycle;
use App\Modules\Content\Models\TwoWheels\SiteSetting;
use App\Modules\Core\Models\Tenant;
use App\Modules\Core\Scopes\TenantScope;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;

class PricingSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-currency-dollar';

    protected string $view = 'filament.pages.pricing-settings';

    protected static ?string $navigationLabel = 'Ceny';

    protected static ?string $title = 'Ustawienia cennika';

    protected static ?int $navigationSort = 55;

    public ?array $data = [];

    public function mount(): void
    {
        $setting = $this->getSiteSetting();

        $this->form->fill([
            'pricing_title' => $setting?->pricing_title ?? 'Cennik',
            'pricing_subtitle' => $setting?->pricing_subtitle,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Nagłówek sekcji cennika')
                    ->schema([
                        TextInput::make('pricing_title')
                            ->label('Tytuł')
                            ->required()
                            ->maxLength(255),

                        Textarea::make('pricing_subtitle')
                            ->label('Podtytuł')
                            ->rows(2)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Section::make('Cennik motocykli')
                    ->description('Ceny są edytowane w zasobie Motocykle. Poniżej podgląd aktualnych cen.')
                    ->schema([
                        Placeholder::make('motorcycle_prices')
                            ->label('')
                            ->content(function (): Htmlable {
                                $motorcycles = Motorcycle::withoutGlobalScope(TenantScope::class)
                                    ->where('published', true)
                                    ->orderBy('name')
                                    ->get(['name', 'price_per_day', 'price_per_week', 'price_per_month', 'deposit']);

                                // KML-0049: render via Blade partial — Tailwind purge poprawnie obejmuje klasy w plikach .blade.php,
                                // co rozwiazuje problem rozsypanego layoutu na TST (klasy w stringach PHP nie byly indeksowane).
                                return new HtmlString(
                                    view('filament.pages._pricing-table', ['motorcycles' => $motorcycles])->render()
                                );
                            })
                            ->columnSpanFull(),
                    ]),

                Section::make('Uwagi cennika')
                    ->description('Zarządzaj uwagami cennika w dedykowanym zasobie.')
                    ->schema([
                        Actions::make([
                            Action::make('manage_pricing_notes')
                                ->label('Zarządzaj uwagami cennika')
                                ->icon('heroicon-o-document-text')
                                ->url('/admin/modules/content/models/two-wheels/pricing-notes')
                                ->color('info'),
                        ]),
                    ]),

                // KML-0049 (UX follow-up): Save action zgodny z Filament native
                // — zamiast customowego sticky div w blade. Renderuje sie w schemie
                // formy, w naturalnym flow, align-left, jak EditRecord.
                Actions::make([
                    Action::make('save')
                        ->label('Zapisz zmiany')
                        ->submit('save')
                        ->color('primary')
                        ->keyBindings(['mod+s']),
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
            'pricing_title' => $data['pricing_title'],
            'pricing_subtitle' => $data['pricing_subtitle'],
        ]);

        Notification::make()
            ->title('Zapisano')
            ->body('Ustawienia cennika zostały zaktualizowane.')
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

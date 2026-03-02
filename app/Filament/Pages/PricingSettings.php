<?php

declare(strict_types=1);

namespace App\Filament\Pages;

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

    protected static ?string $navigationIcon = 'heroicon-o-currency-dollar';

    protected static string $view = 'filament.pages.pricing-settings';

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

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Nagłówek sekcji cennika')
                    ->schema([
                        Forms\Components\TextInput::make('pricing_title')
                            ->label('Tytuł')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\Textarea::make('pricing_subtitle')
                            ->label('Podtytuł')
                            ->rows(2)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Cennik motocykli')
                    ->description('Ceny są edytowane w zasobie Motocykle. Poniżej podgląd aktualnych cen.')
                    ->schema([
                        Forms\Components\Placeholder::make('motorcycle_prices')
                            ->label('')
                            ->content(function (): Htmlable {
                                $motorcycles = Motorcycle::withoutGlobalScope(TenantScope::class)
                                    ->where('published', true)
                                    ->orderBy('name')
                                    ->get(['name', 'price_per_day', 'price_per_week', 'price_per_month', 'deposit']);

                                if ($motorcycles->isEmpty()) {
                                    return new HtmlString('<p class="text-gray-500">Brak opublikowanych motocykli.</p>');
                                }

                                $html = '<div class="overflow-x-auto"><table class="w-full text-sm">';
                                $html .= '<thead><tr class="border-b">';
                                $html .= '<th class="text-left p-2">Motocykl</th>';
                                $html .= '<th class="text-right p-2">Dzień</th>';
                                $html .= '<th class="text-right p-2">Tydzień</th>';
                                $html .= '<th class="text-right p-2">Miesiąc</th>';
                                $html .= '<th class="text-right p-2">Kaucja</th>';
                                $html .= '</tr></thead><tbody>';

                                foreach ($motorcycles as $moto) {
                                    $html .= '<tr class="border-b">';
                                    $html .= '<td class="p-2 font-medium">' . e($moto->name) . '</td>';
                                    $html .= '<td class="p-2 text-right">' . number_format((float) $moto->price_per_day, 2, ',', ' ') . ' zł</td>';
                                    $html .= '<td class="p-2 text-right">' . number_format((float) $moto->price_per_week, 2, ',', ' ') . ' zł</td>';
                                    $html .= '<td class="p-2 text-right">' . number_format((float) $moto->price_per_month, 2, ',', ' ') . ' zł</td>';
                                    $html .= '<td class="p-2 text-right">' . number_format((float) $moto->deposit, 2, ',', ' ') . ' zł</td>';
                                    $html .= '</tr>';
                                }

                                $html .= '</tbody></table></div>';
                                return new HtmlString($html);
                            })
                            ->columnSpanFull(),
                    ]),

                Forms\Components\Section::make('Uwagi cennika')
                    ->description('Zarządzaj uwagami cennika w dedykowanym zasobie.')
                    ->schema([
                        Forms\Components\Actions::make([
                            Forms\Components\Actions\Action::make('manage_pricing_notes')
                                ->label('Zarządzaj uwagami cennika')
                                ->icon('heroicon-o-document-text')
                                ->url('/admin/modules/content/models/two-wheels/pricing-notes')
                                ->color('info'),
                        ]),
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

<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\Correction;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Listing;
use App\Models\Order;
use App\Models\Site;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;

/**
 * Widget z podstawowymi statystykami na Dashboard.
 * Widoczny tylko dla super admina.
 */
class StatsOverview extends BaseWidget
{
    protected static ?int $sort = 1;

    /**
     * Tylko super admin widzi ten widget.
     */
    public static function canView(): bool
    {
        /** @var User|null $user */
        $user = Auth::user();
        return $user instanceof User && ($user->is_super_admin || $user->hasRole('super_admin'));
    }

    protected function getStats(): array
    {
        return [
            Stat::make('Aktywne zlecenia', Order::whereIn('status', ['accepted', 'in_progress', 'delivered'])->count())
                ->description('W realizacji')
                ->descriptionIcon('heroicon-m-clipboard-document-list')
                ->color('warning')
                ->chart([7, 3, 4, 5, 6, 3, 5])
                ->url(route('filament.admin.resources.orders.index', ['tableFilters[status][values][0]' => 'in_progress'])),

            Stat::make('Oczekujące poprawki', Correction::whereIn('status', ['reported', 'accepted'])->count())
                ->description('Do realizacji')
                ->descriptionIcon('heroicon-m-wrench-screwdriver')
                ->color('danger')
                ->url(route('filament.admin.resources.corrections.index')),

            Stat::make('Nowe ogłoszenia', Listing::where('status', 'new')->count())
                ->description('Do przeglądu')
                ->descriptionIcon('heroicon-m-megaphone')
                ->color('info')
                ->url(route('filament.admin.resources.listings.index', ['tableFilters[status][values][0]' => 'new'])),

            Stat::make('Strony Live', Site::where('status', 'live')->count())
                ->description('Na produkcji')
                ->descriptionIcon('heroicon-m-globe-alt')
                ->color('success'),

            Stat::make('Aktywni klienci', Customer::where('status', 'active')->count())
                ->description('Z aktywną współpracą')
                ->descriptionIcon('heroicon-m-user-group')
                ->color('primary'),

            Stat::make('Nieopłacone faktury', Invoice::whereIn('status', ['sent', 'overdue'])->count())
                ->description(
                    Invoice::whereIn('status', ['sent', 'overdue'])->sum('total') . ' PLN'
                )
                ->descriptionIcon('heroicon-m-document-text')
                ->color(Invoice::where('status', 'overdue')->exists() ? 'danger' : 'warning')
                ->url(route('filament.admin.resources.invoices.index', ['tableFilters[status][values][0]' => 'sent'])),

            Stat::make('Przetwarzane szablony', \App\Modules\Generator\Models\Template::whereIn('analysis_status', ['analyzing', 'pending'])->count())
                ->description(
                    \App\Modules\Generator\Models\Template::where('analysis_status', 'analyzing')->count() . ' w trakcie, ' .
                    \App\Modules\Generator\Models\Template::where('analysis_status', 'pending')->count() . ' oczekuje. ' .
                    'Zobacz listę jobów poniżej.'
                )
                ->descriptionIcon('heroicon-m-cog-6-tooth')
                ->color('info'),
        ];
    }
}

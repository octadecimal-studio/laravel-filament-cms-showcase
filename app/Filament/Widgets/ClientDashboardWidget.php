<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Filament\Resources\Modules\Content\Models\TwoWheels\FeatureResource;
use App\Filament\Resources\Modules\Content\Models\TwoWheels\GalleryResource;
use App\Filament\Resources\Modules\Content\Models\TwoWheels\MotorcycleResource;
use App\Filament\Resources\Modules\Content\Models\TwoWheels\SiteSettingResource;
use App\Modules\Core\Models\TenantFeatureAccess;
use Filament\Widgets\Widget;

/**
 * Widget „Szybki dostęp” na dashboardzie klienta.
 * Widoczny tylko dla użytkowników niebędących super adminami.
 */
class ClientDashboardWidget extends Widget
{
    protected static string $view = 'filament.widgets.client-dashboard-widget';

    protected static ?int $sort = 0;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        $user = auth()->user();
        if (! $user) {
            return false;
        }
        // Tylko klienci (nie super admin)
        return ! ($user->is_super_admin || $user->hasRole('super_admin'));
    }

    /**
     * Szybkie linki do sekcji, do których klient ma dostęp.
     *
     * @return array<int, array{label: string, url: string, icon: string}>
     */
    public function getQuickLinks(): array
    {
        $user = auth()->user();
        if (! $user || ! $user->tenant_id) {
            return [];
        }

        $links = [];
        $checks = [
            'motorcycles' => ['Motocykle', MotorcycleResource::class, 'heroicon-o-truck'],
            'gallery' => ['Galeria', GalleryResource::class, 'heroicon-o-photo'],
            'site_settings' => ['Ustawienia strony', SiteSettingResource::class, 'heroicon-o-cog-6-tooth'],
            'features' => ['Zalety', FeatureResource::class, 'heroicon-o-sparkles'],
        ];

        foreach ($checks as $feature => [$label, $resource, $icon]) {
            if (! TenantFeatureAccess::hasAccess($user->tenant_id, $feature, 'view')) {
                continue;
            }
            $links[] = [
                'label' => $label,
                'url' => $resource::getUrl('index'),
                'icon' => $icon,
            ];
        }

        // Rezerwacje (plugin – inna ścieżka)
        if (TenantFeatureAccess::hasAccess($user->tenant_id, 'reservations', 'view')) {
            $reservationResource = \App\Plugins\Reservations\Filament\Resources\ReservationResource::class;
            $links[] = [
                'label' => 'Rezerwacje',
                'url' => $reservationResource::getUrl('index'),
                'icon' => 'heroicon-o-calendar-days',
            ];
        }

        return $links;
    }
}

<?php

declare(strict_types=1);

namespace App\Filament\Resources\TenantAccessResource\Pages;

use App\Filament\Resources\TenantAccessResource;
use App\Modules\Core\Models\Tenant;
use App\Modules\Core\Models\TenantFeatureAccess;
use Filament\Actions;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;

/**
 * Strona zarządzania dostępami dla konkretnego tenanta.
 *
 * Wyświetla macierz checkboxów z funkcjonalnościami pogrupowanymi
 * według kategorii. Umożliwia granularne przydzielanie uprawnień
 * CRUD dla każdej funkcjonalności.
 */
class ManageTenantAccess extends Page implements HasForms
{
    use InteractsWithForms;

    /**
     * Resource.
     */
    protected static string $resource = TenantAccessResource::class;

    /**
     * Widok.
     */
    protected static string $view = 'filament.resources.tenant-access-resource.pages.manage-tenant-access';

    /**
     * Tenant.
     */
    public Tenant $record;

    /**
     * Dane formularza - dostępy.
     *
     * @var array<string, array<string, bool>>
     */
    public array $access = [];

    /**
     * Tytuł strony.
     */
    public function getTitle(): string
    {
        return "Dostępy: {$this->record->name}";
    }

    /**
     * Breadcrumbs.
     *
     * @return array<string, string>
     */
    public function getBreadcrumbs(): array
    {
        return [
            TenantAccessResource::getUrl() => 'Dostępy klientów',
            '' => $this->record->name,
        ];
    }

    /**
     * Mount - inicjalizacja danych.
     */
    public function mount(Tenant $record): void
    {
        $this->record = $record;

        // Załaduj istniejące dostępy
        $existingAccess = TenantFeatureAccess::where('tenant_id', $record->id)->get();

        // Inicjalizuj wszystkie funkcjonalności z domyślnymi wartościami
        foreach (TenantFeatureAccess::FEATURES as $featureKey => $featureConfig) {
            $existing = $existingAccess->firstWhere('feature', $featureKey);

            $this->access[$featureKey] = [
                'can_view' => $existing?->can_view ?? false,
                'can_create' => $existing?->can_create ?? false,
                'can_edit' => $existing?->can_edit ?? false,
                'can_delete' => $existing?->can_delete ?? false,
            ];
        }
    }

    /**
     * Zapisz dostępy.
     */
    public function save(): void
    {
        foreach ($this->access as $featureKey => $permissions) {
            TenantFeatureAccess::setAccess(
                $this->record->id,
                $featureKey,
                [
                    'can_view' => $permissions['can_view'] ?? false,
                    'can_create' => $permissions['can_create'] ?? false,
                    'can_edit' => $permissions['can_edit'] ?? false,
                    'can_delete' => $permissions['can_delete'] ?? false,
                ]
            );
        }

        Notification::make()
            ->title('Zapisano')
            ->body('Dostępy zostały zaktualizowane.')
            ->success()
            ->send();
    }

    /**
     * Zaznacz wszystkie dla grupy.
     */
    public function selectAllForGroup(string $group): void
    {
        foreach (TenantFeatureAccess::FEATURES as $featureKey => $featureConfig) {
            if ($featureConfig['group'] === $group) {
                $this->access[$featureKey] = [
                    'can_view' => true,
                    'can_create' => true,
                    'can_edit' => true,
                    'can_delete' => true,
                ];
            }
        }
    }

    /**
     * Odznacz wszystkie dla grupy.
     */
    public function deselectAllForGroup(string $group): void
    {
        foreach (TenantFeatureAccess::FEATURES as $featureKey => $featureConfig) {
            if ($featureConfig['group'] === $group) {
                $this->access[$featureKey] = [
                    'can_view' => false,
                    'can_create' => false,
                    'can_edit' => false,
                    'can_delete' => false,
                ];
            }
        }
    }

    /**
     * Pobierz funkcjonalności pogrupowane.
     *
     * @return array<string, array<string, array{label: string, key: string}>>
     */
    public function getGroupedFeatures(): array
    {
        $grouped = [];

        foreach (TenantFeatureAccess::FEATURE_GROUPS as $groupKey => $groupLabel) {
            $grouped[$groupKey] = [
                'label' => $groupLabel,
                'features' => [],
            ];
        }

        foreach (TenantFeatureAccess::FEATURES as $featureKey => $featureConfig) {
            $group = $featureConfig['group'];
            if (isset($grouped[$group])) {
                $grouped[$group]['features'][$featureKey] = [
                    'label' => $featureConfig['label'],
                    'key' => $featureKey,
                ];
            }
        }

        // Usuń puste grupy
        return array_filter($grouped, fn ($group) => !empty($group['features']));
    }

    /**
     * Akcje nagłówka.
     *
     * @return array<Actions\Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('back')
                ->label('Powrót do listy')
                ->url(TenantAccessResource::getUrl())
                ->color('gray')
                ->icon('heroicon-o-arrow-left'),
        ];
    }
}

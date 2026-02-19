<?php

declare(strict_types=1);

namespace App\Providers\Filament;

use App\Http\Middleware\UpdateSessionUserId;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use App\Models\Plugin;
use App\Models\UserCustomNavigationItem;
use App\Modules\Core\Models\Tenant;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\HtmlString;
use Filament\Widgets;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

/**
 * Provider konfigurujący panel administracyjny Filament.
 */
class AdminPanelProvider extends PanelProvider
{
    /**
     * Sprawdza czy użytkownik jest super adminem.
     */
    private static function isSuperAdmin(): bool
    {
        $user = auth()->user();
        return $user && ($user->is_super_admin || $user->hasRole('super_admin'));
    }

    /**
     * Sprawdza czy użytkownik ma dostęp do sekcji MotoRent.
     * Dedicated panel — always true.
     */
    private static function canAccessTwoWheels(): bool
    {
        return true;
    }

    /**
     * Pobiera custom navigation items użytkownika.
     *
     * @return array<int, NavigationItem>
     */
    private static function getUserCustomNavigationItems(): array
    {
        try {
            $user = auth()->user();
            if (!$user) {
                return [];
            }

            $items = [];
            // Użyj fresh() aby uniknąć problemów z cache relacji
            $customItems = $user->fresh()->customNavigationItems()
                ->where('is_active', true)
                ->where('is_pinned_to_topbar', false) // Te w górnym menu obsłużymy osobno
                ->orderBy('sort_order')
                ->get();

            foreach ($customItems as $item) {
                $items[] = NavigationItem::make('custom_nav_'.$item->id)
                    ->label($item->label)
                    ->icon($item->icon ?? 'heroicon-o-link')
                    ->url($item->url)
                    ->group($item->group)
                    ->sort($item->sort_order + 1000) // Custom items na końcu
                    ->openUrlInNewTab($item->open_in_new_tab);
            }

            return $items;
        } catch (\Exception $e) {
            // Jeśli błąd (np. tabela nie istnieje), zwróć pustą tablicę
            return [];
        }
    }

    /**
     * Pobiera włączone pluginy z bazy danych.
     *
     * @return array<int, \Filament\Panel\Concerns\HasPlugins>
     */
    private static function getEnabledPluginsFromDatabase(): array
    {
        try {
            $plugins = [];
            $enabledPlugins = Plugin::where('is_enabled', true)
                ->where('is_installed', true)
                ->whereNotNull('class_name')
                ->get();

            foreach ($enabledPlugins as $plugin) {
                try {
                    // Sprawdź kompatybilność PRZED próbą utworzenia instancji
                    $service = app(\App\Modules\Plugins\Services\PluginService::class);
                    $compatibility = $service->checkCompatibility($plugin->package, $plugin->class_name);
                    
                    if (!$compatibility['compatible']) {
                        Log::warning("Pomijanie niekompatybilnego pluginu {$plugin->package}: " . $compatibility['message']);
                        continue;
                    }
                    
                    $instance = $plugin->getPluginInstance();
                    if ($instance) {
                        $plugins[] = $instance;
                    }
                } catch (\Throwable $e) {
                    // Używamy Throwable aby złapać również Fatal Errors
                    Log::error("Błąd ładowania pluginu {$plugin->package}: " . $e->getMessage());
                    continue; // Pomijamy ten plugin i kontynuujemy z następnymi
                }
            }

            return $plugins;
        } catch (\Throwable $e) {
            // Jeśli błąd (np. tabela nie istnieje, błąd bazy), zwróć pustą tablicę
            Log::warning("Błąd pobierania pluginów z bazy: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Pobiera style CSS dla tapety użytkownika.
     */
    private static function getUserWallpaperStyles(): string
    {
        try {
            $user = auth()->user();
            if (!$user || !$user->wallpaper_url) {
                return '';
            }

            $wallpaperUrl = Storage::disk('public')->url($user->wallpaper_url);
            return '.fi-body { background-image: url("' . e($wallpaperUrl) . '") !important; background-size: cover !important; background-position: center !important; background-attachment: fixed !important; }';
        } catch (\Exception $e) {
            return '';
        }
    }

    /**
     * Generuje HTML dla górnego menu z zakładkami.
     */
    private static function getTopbarTabsHtml(): string
    {
        try {
            $user = auth()->user();
            if (!$user) {
                return '';
            }

            // Użyj fresh() aby uniknąć problemów z cache
            $pinnedItems = $user->customNavigationItems()
                ->where('is_active', true)
                ->where('is_pinned_to_topbar', true)
                ->orderBy('sort_order')
                ->get();

            if ($pinnedItems->isEmpty()) {
                return '';
            }

            $tabsHtml = '<div id="custom-topbar-tabs" style="position: fixed; top: 64px; left: 0; right: 0; z-index: 40; display: flex; gap: 0.5rem; padding: 0.5rem 1rem; background: rgb(229, 231, 235); border-bottom: 1px solid rgba(0,0,0,0.1); overflow-x: auto;">';
            foreach ($pinnedItems as $item) {
                $iconHtml = '';
                if ($item->icon) {
                    // Uproszczona ikona SVG
                    $iconHtml = '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>';
                }
                $target = $item->open_in_new_tab ? 'target="_blank"' : '';
                $tabsHtml .= '<a href="' . e($item->url) . '" ' . $target . ' style="display: flex; align-items: center; gap: 0.5rem; padding: 0.5rem 1rem; background: white; border-radius: 0.375rem; text-decoration: none; color: #374151; font-size: 0.875rem; border: 1px solid rgba(0,0,0,0.1); white-space: nowrap; transition: all 0.2s;" onmouseover="this.style.background=\'#f3f4f6\'" onmouseout="this.style.background=\'white\'">' . $iconHtml . '<span>' . e($item->label) . '</span></a>';
            }
            $tabsHtml .= '</div>';
            $tabsHtml .= '<style>
                #custom-topbar-tabs {
                    margin-left: var(--sidebar-width, 0);
                }
                .fi-sidebar[data-sidebar-collapsible="collapsed"] ~ * #custom-topbar-tabs {
                    margin-left: var(--sidebar-width-collapsed, 0);
                }
            </style>';

            return $tabsHtml;
        } catch (\Exception $e) {
            return '';
        }
    }

    /**
     * Generuje HTML dla avatara użytkownika w menu.
     */
    private static function getUserAvatarHtml(): string
    {
        try {
            $user = auth()->user();
            if (!$user || !$user->avatar_url) {
                return '';
            }

            $avatarUrl = Storage::disk('public')->url($user->avatar_url);
            $escapedAvatarUrl = addslashes($avatarUrl);
            return '<script>
                document.addEventListener("DOMContentLoaded", function() {
                    const avatarUrl = "' . e($avatarUrl) . '";
                    // Filament automatycznie używa getFilamentAvatarUrl() jeśli model implementuje HasAvatar
                    // Ale możemy też ręcznie ustawić
                    setTimeout(function() {
                        const avatarElements = document.querySelectorAll(".fi-user-menu-trigger-avatar, [data-user-menu-trigger] img, .fi-avatar img");
                        avatarElements.forEach(function(el) {
                            if (el.tagName === "IMG") {
                                el.src = avatarUrl;
                                el.onerror = null;
                            } else {
                                el.style.backgroundImage = "url(" + avatarUrl + ")";
                                el.style.backgroundSize = "cover";
                                el.style.backgroundPosition = "center";
                            }
                        });
                    }, 100);
                });
            </script>';
        } catch (\Exception $e) {
            return '';
        }
    }

    /**
     * Generuje HTML dla menu z lokacji "header" w topbar panelu Filament.
     * Gdy FilamentMenuBuilder nie jest zainstalowany (np. na produkcji), zwraca ''.
     */
    private static function getHeaderMenuHtml(): string
    {
        if (!class_exists(\Datlechin\FilamentMenuBuilder\Models\Menu::class)) {
            return '';
        }
        try {
            $menu = \Datlechin\FilamentMenuBuilder\Models\Menu::location('header');
            
            if (!$menu || !$menu->is_visible || $menu->menuItems->isEmpty()) {
                return '';
            }

            // Załaduj relacje
            $menu->load('menuItems.children');

            $menuHtml = '<div id="filament-header-menu" style="display: flex; align-items: center; gap: 1rem; margin-left: 1rem;">';
            
            foreach ($menu->menuItems as $item) {
                $target = $item->target?->value ?? '_self';
                $url = $item->url ?? '#';
                $targetAttr = $target === '_blank' ? 'target="_blank" rel="noopener noreferrer"' : '';
                
                $menuHtml .= '<a href="' . e($url) . '" ' . $targetAttr . ' style="text-decoration: none; color: #374151; font-size: 0.875rem; font-weight: 500; padding: 0.5rem 0.75rem; border-radius: 0.375rem; transition: all 0.2s; white-space: nowrap;" onmouseover="this.style.backgroundColor=\'rgba(0,0,0,0.05)\'" onmouseout="this.style.backgroundColor=\'transparent\'">' . e($item->title) . '</a>';
            }
            
            $menuHtml .= '</div>';
            $menuHtml .= '<script>
                document.addEventListener("DOMContentLoaded", function() {
                    // Wstaw menu do topbar, przed user menu
                    const topbar = document.querySelector(".fi-topbar > div");
                    const headerMenu = document.getElementById("filament-header-menu");
                    if (topbar && headerMenu) {
                        const userMenu = topbar.querySelector(".fi-user-menu");
                        if (userMenu) {
                            topbar.insertBefore(headerMenu, userMenu);
                        } else {
                            topbar.appendChild(headerMenu);
                        }
                    }
                });
            </script>';

            return $menuHtml;
        } catch (\Exception $e) {
            Log::warning('Błąd podczas renderowania menu header: ' . $e->getMessage());
            return '';
        }
    }

    /**
     * Konfiguracja panelu Filament.
     */
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->passwordReset()
            ->emailVerification()

            // Multi-tenancy wyłączone - RBAC zapewnia izolację danych
            // ->tenant(Tenant::class, slugAttribute: 'slug')
            // ->tenantRoutePrefix('tenant')

            // Branding - kolory Octadecimal (neutralna paleta stone)
            ->colors([
                'primary' => Color::Zinc, // Neutralny, zbliżony do stone
                'gray' => Color::Zinc,    // Neutralna paleta
                'danger' => Color::Rose,
                'warning' => Color::Amber,
                'success' => Color::Emerald,
                'info' => Color::Sky,
            ])
            ->brandName('MotoRent Demo')
            ->brandLogo(asset('storage/motorent/logo.jpg'))
            ->darkModeBrandLogo(asset('storage/motorent/logo.jpg'))
            ->homeUrl(config('app.motorent_frontend_url', 'https://example-rental.test') . '/')
            ->favicon(asset('favicon.ico'))

            // Dark mode domyślnie
            ->darkMode(true)

            // User menu - linki
            ->userMenuItems([
                \Filament\Navigation\MenuItem::make()
                    ->label('Mój profil')
                    ->url('/admin/edit-profile')
                    ->icon('heroicon-o-user-circle'),
            ])

            // Tło: biała strona główna; pasek nav (sidebar + topbar) – rgb(229, 231, 235)
            ->renderHook(PanelsRenderHook::STYLES_AFTER, fn (): HtmlString => new HtmlString(
                '<style>'
                . self::getUserWallpaperStyles()
                . '.fi-body { background-color: #fff !important; } .dark .fi-body { background-color: #fff !important; }'
                . '.fi-sidebar, .fi-sidebar-header, .fi-topbar, .fi-topbar > div { background-color: rgb(229, 231, 235) !important; }'
                . '.dark .fi-sidebar, .dark .fi-sidebar-header, .dark .fi-topbar, .dark .fi-topbar > div { background-color: rgb(229, 231, 235) !important; }'
                
                // === DODATKOWE OPCJE POZYCJONOWANIA SIDEBAR ===
                
                // Opcja A: Przenieś sidebar na prawą stronę (odkomentuj jeśli potrzebujesz)
                // '.fi-main { flex-direction: row-reverse !important; }'
                // '.fi-sidebar { order: 2 !important; left: auto !important; right: 0 !important; }'
                // '.fi-main-content { order: 1 !important; margin-left: 0 !important; margin-right: var(--sidebar-width) !important; }'
                // '.fi-sidebar[data-sidebar-collapsible="collapsed"] ~ .fi-main-content { margin-right: var(--sidebar-width-collapsed) !important; }'
                
                // Opcja B: Sidebar wysuwane z lewej (overlay - nakłada się na treść)
                // '.fi-sidebar { position: fixed !important; z-index: 50 !important; }'
                // '.fi-sidebar[data-sidebar-collapsible="collapsed"] { transform: translateX(-100%) !important; }'
                // '.fi-main-content { margin-left: 0 !important; }'
                
                // Opcja C: Sidebar wysuwane z prawej (overlay)
                // '.fi-sidebar { position: fixed !important; z-index: 50 !important; right: 0 !important; left: auto !important; }'
                // '.fi-sidebar[data-sidebar-collapsible="collapsed"] { transform: translateX(100%) !important; }'
                // '.fi-main-content { margin-left: 0 !important; margin-right: 0 !important; }'
                
                // Opcja D: Sidebar zawsze widoczne (nie zwijane)
                // '.fi-sidebar-collapse-button { display: none !important; }'
                
                . '</style>'
            ))
            ->renderHook(PanelsRenderHook::SCRIPTS_AFTER, fn (): HtmlString => new HtmlString(''))
            ->renderHook(PanelsRenderHook::BODY_START, fn (): HtmlString => new HtmlString(
                self::getTopbarTabsHtml() . self::getUserAvatarHtml() . self::getHeaderMenuHtml()
            ))
            ->renderHook(PanelsRenderHook::GLOBAL_SEARCH_AFTER, fn (): HtmlString => new HtmlString(
                self::getHeaderMenuHtml()
            ))

            // Odkrywanie zasobów Filament
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverResources(in: app_path('Modules/Content'), for: 'App\\Modules\\Content')
            ->discoverResources(in: app_path('Plugins'), for: 'App\\Plugins')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Pages\Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                Widgets\AccountWidget::class,
            ])

            // Middleware
            ->middleware($this->getMiddleware())
            ->authMiddleware([
                Authenticate::class,
            ])
            // Tenant middleware wyłączone
            // ->tenantMiddleware([
            //     EnsureTenantSession::class,
            // ], isPersistent: true)

            // SPA mode wyłączone - powoduje problemy z CSRF
            // ->spa()

            // Maksymalna szerokość kontentu
            ->maxContentWidth('full')

            // === KONFIGURACJA NAVIGATION/SIDEBAR ===
            
            // Opcja 1: Top navigation zamiast sidebar (menu na górze)
            // ->topNavigation()
            
            // Opcja 2: Sidebar zwijane na desktop (pokazuje tylko ikony z tooltipami)
            ->sidebarCollapsibleOnDesktop()
            
            // Opcja 3: Sidebar całkowicie zwijane (tylko przycisk toggle)
            // ->sidebarFullyCollapsibleOnDesktop()
            
            // Opcja 4: Sidebar zawsze zwijane (domyślnie)
            // ->sidebarCollapsibleOnDesktop()
            // ->sidebarCollapsedByDefault()
            
            // Opcja 5: Collapsible navigation groups (zwijane grupy w menu)
            // UWAGA: Jeśli używasz navigationGroups, wszystkie grupy muszą być zdefiniowane
            // W przeciwnym razie Resources z niezdefiniowanymi grupami mogą nie być widoczne
            // ->navigationGroups([
            //     NavigationGroup::make('CRM')
            //         ->label('CRM')
            //         ->icon('heroicon-o-briefcase')
            //         ->collapsible(true),
            //     NavigationGroup::make('Content')
            //         ->label('Content')
            //         ->icon('heroicon-o-document-text')
            //         ->collapsible(true),
            //     NavigationGroup::make('Generator')
            //         ->label('Generator')
            //         ->icon('heroicon-o-sparkles')
            //         ->collapsible(true),
            //     NavigationGroup::make('Deployment')
            //         ->label('Deployment')
            //         ->icon('heroicon-o-rocket-launch')
            //         ->collapsible(true),
            //     NavigationGroup::make('System')
            //         ->label('System')
            //         ->icon('heroicon-o-cog-6-tooth')
            //         ->collapsible(false), // Nie zwijane
            // ])

            // Rejestracja i profil - używamy custom strony zamiast domyślnej
            // ->profile() // Wyłączone - używamy custom EditProfile page

            // Filament Shield - zarządzanie rolami i uprawnieniami
            ->plugin(FilamentShieldPlugin::make())

            // Niestandardowe pozycje menu
            ->navigationItems(array_merge(
                [
                    NavigationItem::make('edit-profile')
                        ->label('Mój profil')
                        ->icon('heroicon-o-user-circle')
                        ->url('/admin/edit-profile')
                        ->sort(-5),
                    NavigationItem::make('Mailbox')
                        ->label('Mailbox')
                        ->icon('heroicon-o-envelope')
                        ->url('https://mail.ovh.net/roundcube/')
                        ->sort(95)
                        ->openUrlInNewTab(),
                ],
                // Dodaj custom navigation items użytkownika
                self::getUserCustomNavigationItems()
            ));
    }

    /**
     * Zwraca listę middleware dla panelu.
     *
     * @return array<int, string>
     */
    private function getMiddleware(): array
    {
        $middleware = [
            EncryptCookies::class,
            AddQueuedCookiesToResponse::class,
            StartSession::class,
            AuthenticateSession::class, // Włączone - naprawione zapisywanie user_id
            ShareErrorsFromSession::class,
            VerifyCsrfToken::class,
            SubstituteBindings::class,
            DisableBladeIconComponents::class,
            DispatchServingFilamentEvent::class,
        ];

        // Dodaj UpdateSessionUserId tylko jeśli jest włączone w config
        if (config('session.update_user_id_enabled', false)) {
            $middleware[] = UpdateSessionUserId::class;
        }

        return $middleware;
    }
}

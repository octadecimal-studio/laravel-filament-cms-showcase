<?php

declare(strict_types=1);

namespace App\Filament\Concerns;

use Illuminate\Support\Facades\Auth;

/**
 * Trait dodający automatyczne zarządzanie widocznością w nawigacji
 * na podstawie uprawnień Spatie Permission.
 *
 * Użycie:
 * 1. Dodaj trait do Resource/Page/Widget
 * 2. Ustaw właściwość $permissionPrefix (np. 'customers', 'sites')
 * 3. Navigation będzie widoczne tylko jeśli użytkownik ma uprawnienie "{prefix}.view_any"
 *
 * Przykład:
 * ```php
 * class CustomerResource extends Resource
 * {
 *     use HasNavigationPermission;
 *
 *     protected static string $permissionPrefix = 'customers';
 *     // ...
 * }
 * ```
 *
 * UWAGA: Trait NIE definiuje właściwości $permissionPrefix - każda klasa
 * MUSI zdefiniować własną, aby uniknąć konfliktów definicji właściwości.
 */
trait HasNavigationPermission
{
    /**
     * Sprawdza czy Resource/Page/Widget powinien być widoczny w nawigacji.
     *
     * Domyślnie sprawdza uprawnienie "{prefix}.view_any".
     * Można nadpisać w klasie dla niestandardowej logiki.
     *
     * WYMAGANE: Klasa używająca traitu MUSI zdefiniować:
     * protected static string $permissionPrefix = 'customers';
     */
    public static function shouldRegisterNavigation(): bool
    {
        /** @var \App\Models\User|null $user */
        $user = \Illuminate\Support\Facades\Auth::user();

        if (! $user) {
            return false;
        }

        // Super admin widzi wszystko
        if ($user->is_super_admin || $user->hasRole('super_admin')) {
            return true;
        }

        // Sprawdź czy klasa zdefiniowała $permissionPrefix
        // Używamy property_exists() aby uniknąć konfliktu definicji właściwości
        if (!property_exists(static::class, 'permissionPrefix') || !isset(static::$permissionPrefix) || static::$permissionPrefix === '') {
            // Jeśli nie ma prefixu, użyj domyślnej logiki (zwróć true)
            return true;
        }

        // Sprawdź uprawnienie view_any
        return $user->can(static::$permissionPrefix . '.view_any');
    }

    /**
     * Pobiera prefix uprawnień.
     */
    public static function getPermissionPrefix(): ?string
    {
        if (!property_exists(static::class, 'permissionPrefix')) {
            return null;
        }
        return static::$permissionPrefix ?? null;
    }
}

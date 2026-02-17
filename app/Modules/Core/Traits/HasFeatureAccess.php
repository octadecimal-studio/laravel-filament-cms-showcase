<?php

declare(strict_types=1);

namespace App\Modules\Core\Traits;

use App\Modules\Core\Models\TenantFeatureAccess;

/**
 * Trait do sprawdzania dostępu do funkcjonalności w Filament Resources.
 *
 * Użycie w Resource:
 * ```php
 * use HasFeatureAccess;
 *
 * protected static string $featureName = 'motorcycles';
 *
 * public static function shouldRegisterNavigation(): bool
 * {
 *     return static::canAccessFeature();
 * }
 * ```
 */
trait HasFeatureAccess
{
    /**
     * Sprawdza czy użytkownik ma dostęp do funkcjonalności (nawigacja).
     * Super admin zawsze ma dostęp.
     */
    protected static function canAccessFeature(): bool
    {
        $user = auth()->user();

        if (!$user) {
            return false;
        }

        // Super admin widzi wszystko
        if ($user->is_super_admin || $user->hasRole('super_admin')) {
            return true;
        }

        // Sprawdź dostęp tenanta do funkcjonalności
        if (!isset(static::$featureName) || !$user->tenant_id) {
            return false;
        }

        return TenantFeatureAccess::hasAccess($user->tenant_id, static::$featureName, 'view');
    }

    /**
     * Sprawdza czy użytkownik może tworzyć rekordy.
     */
    protected static function canCreateFeature(): bool
    {
        $user = auth()->user();

        if (!$user) {
            return false;
        }

        if ($user->is_super_admin || $user->hasRole('super_admin')) {
            return true;
        }

        if (!isset(static::$featureName) || !$user->tenant_id) {
            return false;
        }

        return TenantFeatureAccess::hasAccess($user->tenant_id, static::$featureName, 'create');
    }

    /**
     * Sprawdza czy użytkownik może edytować rekordy.
     */
    protected static function canEditFeature(): bool
    {
        $user = auth()->user();

        if (!$user) {
            return false;
        }

        if ($user->is_super_admin || $user->hasRole('super_admin')) {
            return true;
        }

        if (!isset(static::$featureName) || !$user->tenant_id) {
            return false;
        }

        return TenantFeatureAccess::hasAccess($user->tenant_id, static::$featureName, 'edit');
    }

    /**
     * Sprawdza czy użytkownik może usuwać rekordy.
     */
    protected static function canDeleteFeature(): bool
    {
        $user = auth()->user();

        if (!$user) {
            return false;
        }

        if ($user->is_super_admin || $user->hasRole('super_admin')) {
            return true;
        }

        if (!isset(static::$featureName) || !$user->tenant_id) {
            return false;
        }

        return TenantFeatureAccess::hasAccess($user->tenant_id, static::$featureName, 'delete');
    }
}

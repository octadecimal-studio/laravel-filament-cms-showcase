<?php

declare(strict_types=1);

namespace App\Filament\Resources\TenantAccessResource\Pages;

use App\Filament\Resources\TenantAccessResource;
use Filament\Resources\Pages\ListRecords;

/**
 * Strona z listą tenantów do zarządzania dostępami.
 */
class ListTenantAccess extends ListRecords
{
    /**
     * Resource.
     */
    protected static string $resource = TenantAccessResource::class;

    /**
     * Tytuł strony.
     */
    protected static ?string $title = 'Zarządzanie dostępami klientów';

    /**
     * Nagłówek akcji.
     *
     * @return array<\Filament\Actions\Action>
     */
    protected function getHeaderActions(): array
    {
        return [];
    }
}

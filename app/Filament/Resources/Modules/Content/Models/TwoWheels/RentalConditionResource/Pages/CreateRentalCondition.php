<?php

declare(strict_types=1);

namespace App\Filament\Resources\Modules\Content\Models\TwoWheels\RentalConditionResource\Pages;

use App\Filament\Resources\Modules\Content\Models\TwoWheels\RentalConditionResource;
use App\Modules\Core\Models\Tenant;
use Filament\Resources\Pages\CreateRecord;

class CreateRentalCondition extends CreateRecord
{
    protected static string $resource = RentalConditionResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->ensureCurrentTenant();
        return $data;
    }

    private function ensureCurrentTenant(): void
    {
        if (app()->bound('current_tenant')) {
            return;
        }
        $user = auth()->user();
        $tenantId = $user?->tenant_id
            ?? Tenant::where('slug', 'demo-studio')->where('is_active', true)->value('id')
            ?? Tenant::where('is_active', true)->value('id');
        if ($tenantId) {
            $tenant = Tenant::find($tenantId);
            if ($tenant) {
                app()->instance('current_tenant', $tenant);
            }
        }
    }
}

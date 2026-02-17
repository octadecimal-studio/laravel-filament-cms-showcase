<?php

namespace App\Filament\Resources\Modules\Content\Models\TwoWheels\SiteSettingResource\Pages;

use App\Filament\Resources\Modules\Content\Models\TwoWheels\SiteSettingResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSiteSettings extends ListRecords
{
    protected static string $resource = SiteSettingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Filament\Resources\Modules\Content\Models\SiteContentResource\Pages;

use App\Filament\Resources\Modules\Content\Models\SiteContentResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSiteContents extends ListRecords
{
    protected static string $resource = SiteContentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}

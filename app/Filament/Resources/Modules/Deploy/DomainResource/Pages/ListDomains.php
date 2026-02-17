<?php

namespace App\Filament\Resources\Modules\Deploy\DomainResource\Pages;

use App\Filament\Resources\Modules\Deploy\DomainResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListDomains extends ListRecords
{
    protected static string $resource = DomainResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}

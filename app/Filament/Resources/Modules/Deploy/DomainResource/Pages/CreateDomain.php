<?php

namespace App\Filament\Resources\Modules\Deploy\DomainResource\Pages;

use App\Filament\Resources\Modules\Deploy\DomainResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateDomain extends CreateRecord
{
    protected static string $resource = DomainResource::class;
}

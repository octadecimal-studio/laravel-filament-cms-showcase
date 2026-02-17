<?php

declare(strict_types=1);

namespace App\Filament\Resources\Modules\Content\Models\ContentTemplateResource\Pages;

use App\Filament\Resources\Modules\Content\Models\ContentTemplateResource;
use Filament\Resources\Pages\CreateRecord;

class CreateContentTemplate extends CreateRecord
{
    protected static string $resource = ContentTemplateResource::class;
}

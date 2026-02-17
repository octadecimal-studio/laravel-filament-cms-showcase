<?php

declare(strict_types=1);

namespace App\Filament\Resources\Modules\Content\Models\ContentBlockResource\Pages;

use App\Filament\Resources\Modules\Content\Models\ContentBlockResource;
use Filament\Resources\Pages\CreateRecord;

class CreateContentBlock extends CreateRecord
{
    protected static string $resource = ContentBlockResource::class;
}

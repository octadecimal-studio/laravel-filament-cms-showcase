<?php

declare(strict_types=1);

namespace App\Filament\Resources\Modules\Plugins\PluginResource\Pages;

use App\Filament\Resources\Modules\Plugins\PluginResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPlugin extends EditRecord
{
    protected static string $resource = PluginResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}

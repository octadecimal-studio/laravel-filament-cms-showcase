<?php

declare(strict_types=1);

namespace App\Filament\Resources\Modules\Content\Models\ContentBlockResource\Pages;

use App\Filament\Resources\Modules\Content\Models\ContentBlockResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditContentBlock extends EditRecord
{
    protected static string $resource = ContentBlockResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}

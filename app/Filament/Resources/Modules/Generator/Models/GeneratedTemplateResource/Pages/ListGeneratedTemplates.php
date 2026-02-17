<?php

declare(strict_types=1);

namespace App\Filament\Resources\Modules\Generator\Models\GeneratedTemplateResource\Pages;

use App\Filament\Resources\Modules\Generator\Models\GeneratedTemplateResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListGeneratedTemplates extends ListRecords
{
    protected static string $resource = GeneratedTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('generate')
                ->label('Wygeneruj nowy')
                ->icon('heroicon-o-plus')
                ->url(route('filament.admin.pages.ai-template-generator')),
        ];
    }
}

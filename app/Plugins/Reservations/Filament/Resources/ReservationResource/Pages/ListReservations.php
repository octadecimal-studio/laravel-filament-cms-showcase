<?php

declare(strict_types=1);

namespace App\Plugins\Reservations\Filament\Resources\ReservationResource\Pages;

use Filament\Actions\CreateAction;
use App\Plugins\Reservations\Filament\Resources\ReservationResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListReservations extends ListRecords
{
    protected static string $resource = ReservationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}

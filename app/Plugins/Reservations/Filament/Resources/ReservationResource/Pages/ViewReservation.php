<?php

declare(strict_types=1);

namespace App\Plugins\Reservations\Filament\Resources\ReservationResource\Pages;

use App\Plugins\Reservations\Filament\Resources\ReservationResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewReservation extends ViewRecord
{
    protected static string $resource = ReservationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}

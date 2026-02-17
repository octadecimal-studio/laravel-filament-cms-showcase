<?php

declare(strict_types=1);

namespace App\Plugins\Reservations\Filament\Resources\ReservationResource\Pages;

use App\Plugins\Reservations\Filament\Resources\ReservationResource;
use Filament\Resources\Pages\CreateRecord;

class CreateReservation extends CreateRecord
{
    protected static string $resource = ReservationResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}

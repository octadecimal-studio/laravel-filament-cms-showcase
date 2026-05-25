<?php

declare(strict_types=1);

namespace App\Observers;

use App\Services\GoogleCalendarService;
use Illuminate\Support\Facades\Log;
use Octadecimal\Rental\Models\AvailabilitySlot;

class AvailabilitySlotObserver
{
    public function created(AvailabilitySlot $slot): void
    {
        try {
            $eventId = app(GoogleCalendarService::class)->createAvailabilitySlotEvent($slot);

            if ($eventId) {
                $slot->updateQuietly(['google_calendar_event_id' => $eventId]);
            }
        } catch (\Throwable $e) {
            Log::warning('GoogleCalendar: błąd sync blokady po created', [
                'slot_id' => $slot->id,
                'error'   => $e->getMessage(),
            ]);
        }
    }

    public function updated(AvailabilitySlot $slot): void
    {
        if (! $slot->wasChanged(['start_date', 'end_date', 'reason', 'is_blocked'])) {
            return;
        }

        try {
            app(GoogleCalendarService::class)->updateAvailabilitySlotEvent($slot);
        } catch (\Throwable $e) {
            Log::warning('GoogleCalendar: błąd sync blokady po updated', [
                'slot_id' => $slot->id,
                'error'   => $e->getMessage(),
            ]);
        }
    }

    public function deleted(AvailabilitySlot $slot): void
    {
        $eventId = $slot->google_calendar_event_id;

        if (! $eventId) {
            return;
        }

        try {
            app(GoogleCalendarService::class)->deleteEvent($eventId);
        } catch (\Throwable $e) {
            Log::warning('GoogleCalendar: błąd usuwania eventu blokady', [
                'slot_id'  => $slot->id,
                'event_id' => $eventId,
                'error'    => $e->getMessage(),
            ]);
        }
    }
}

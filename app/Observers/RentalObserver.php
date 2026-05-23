<?php

declare(strict_types=1);

namespace App\Observers;

use App\Mail\AdminRentalNotification;
use App\Mail\RentalReceived;
use App\Modules\Content\Models\TwoWheels\SiteSetting;
use App\Modules\Core\Scopes\TenantScope;
use App\Services\GoogleCalendarService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Octadecimal\Rental\Models\Rental;

class RentalObserver
{
    public function created(Rental $rental): void
    {
        $this->notifyAdmin($rental);
        $this->notifyCustomer($rental);
        $this->syncToCalendar($rental);
    }

    public function updated(Rental $rental): void
    {
        $statusChanged = $rental->wasChanged('status');
        $datesChanged  = $rental->wasChanged(['start_date', 'end_date']);

        if (! $statusChanged && ! $datesChanged) {
            return;
        }

        if ($statusChanged && in_array($rental->status, ['cancelled', 'expired'], true)) {
            $this->deleteCalendarEvent($rental);

            return;
        }

        if ($statusChanged || $datesChanged) {
            $this->updateCalendarEvent($rental);
        }
    }

    private function notifyAdmin(Rental $rental): void
    {
        try {
            $setting = SiteSetting::withoutGlobalScope(TenantScope::class)->first();
            $adminEmail = $setting?->reservation_notification_email;

            if (empty($adminEmail)) {
                return;
            }

            Mail::to($adminEmail)->send(new AdminRentalNotification($rental));
        } catch (\Throwable $e) {
            Log::warning('AdminRentalNotification mail failed', [
                'rental_id' => $rental->id,
                'error'     => $e->getMessage(),
            ]);
        }
    }

    private function notifyCustomer(Rental $rental): void
    {
        if (empty($rental->email)) {
            return;
        }

        try {
            Mail::to($rental->email)->send(new RentalReceived($rental));
        } catch (\Throwable $e) {
            Log::warning('RentalReceived mail failed', [
                'rental_id' => $rental->id,
                'email'     => $rental->email,
                'error'     => $e->getMessage(),
            ]);
        }
    }

    private function syncToCalendar(Rental $rental): void
    {
        try {
            $eventId = app(GoogleCalendarService::class)->createEvent($rental);

            if ($eventId) {
                $meta = $rental->meta ?? [];
                $meta['google_calendar_event_id'] = $eventId;
                $rental->updateQuietly(['meta' => $meta]);
            }
        } catch (\Throwable $e) {
            Log::warning('GoogleCalendar: błąd sync po created', [
                'rental_id' => $rental->id,
                'error'     => $e->getMessage(),
            ]);
        }
    }

    private function updateCalendarEvent(Rental $rental): void
    {
        try {
            app(GoogleCalendarService::class)->updateEvent($rental);
        } catch (\Throwable $e) {
            Log::warning('GoogleCalendar: błąd sync po updated', [
                'rental_id' => $rental->id,
                'error'     => $e->getMessage(),
            ]);
        }
    }

    private function deleteCalendarEvent(Rental $rental): void
    {
        $eventId = $rental->meta['google_calendar_event_id'] ?? null;

        if (! $eventId) {
            return;
        }

        try {
            app(GoogleCalendarService::class)->deleteEvent($eventId);

            $meta = $rental->meta ?? [];
            unset($meta['google_calendar_event_id']);
            $rental->updateQuietly(['meta' => $meta]);
        } catch (\Throwable $e) {
            Log::warning('GoogleCalendar: błąd usuwania eventu po cancelled', [
                'rental_id' => $rental->id,
                'error'     => $e->getMessage(),
            ]);
        }
    }
}

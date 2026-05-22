<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\GoogleCalendarSetting;
use Google\Client;
use Google\Service\Calendar;
use Google\Service\Calendar\Event;
use Google\Service\Calendar\EventDateTime;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Octadecimal\Rental\Models\Rental;

class GoogleCalendarService
{
    private const SCOPES = [Calendar::CALENDAR_EVENTS];

    public function isConnected(): bool
    {
        return GoogleCalendarSetting::instance()->isConnected();
    }

    public function hasCredentials(): bool
    {
        return GoogleCalendarSetting::instance()->hasCredentials();
    }

    public function getAuthUrl(): string
    {
        $settings = GoogleCalendarSetting::instance();
        $client = new Client();
        $client->setScopes(self::SCOPES);
        $client->setClientId($settings->client_id ?? '');
        $client->setClientSecret($settings->client_secret ?? '');
        $client->setRedirectUri(route('google-calendar.callback'));
        $client->setAccessType('offline');
        $client->setPrompt('consent');

        return $client->createAuthUrl();
    }

    public function handleCallback(string $code): void
    {
        $settings = GoogleCalendarSetting::instance();
        $client = new Client();
        $client->setClientId($settings->client_id ?? '');
        $client->setClientSecret($settings->client_secret ?? '');
        $client->setRedirectUri(route('google-calendar.callback'));

        $token = $client->fetchAccessTokenWithAuthCode($code);

        if (isset($token['error'])) {
            throw new \RuntimeException('Google OAuth error: ' . ($token['error_description'] ?? $token['error']));
        }

        $this->storeTokens($settings, $token);
        $settings->update(['connected_at' => now()]);
    }

    public function disconnect(): void
    {
        GoogleCalendarSetting::instance()->update([
            'access_token'     => null,
            'refresh_token'    => null,
            'calendar_id'      => null,
            'token_expires_at' => null,
            'connected_at'     => null,
        ]);
    }

    public function listCalendars(): array
    {
        try {
            $service = new Calendar($this->buildClient());
            $result = [];

            foreach ($service->calendarList->listCalendarList()->getItems() as $cal) {
                $result[$cal->getId()] = $cal->getSummary();
            }

            return $result;
        } catch (\Throwable $e) {
            Log::warning('GoogleCalendar: nie można pobrać listy kalendarzy', ['error' => $e->getMessage()]);

            return [];
        }
    }

    public function createEvent(Rental $rental): ?string
    {
        if (! $this->isConnected()) {
            return null;
        }

        try {
            $settings = GoogleCalendarSetting::instance();
            $created = (new Calendar($this->buildClient()))
                ->events
                ->insert($settings->calendar_id, $this->buildEvent($rental));

            return $created->getId();
        } catch (\Throwable $e) {
            Log::warning('GoogleCalendar: błąd tworzenia eventu', [
                'rental_id' => $rental->id,
                'error'     => $e->getMessage(),
            ]);

            return null;
        }
    }

    public function updateEvent(Rental $rental): void
    {
        $eventId = $rental->meta['google_calendar_event_id'] ?? null;

        if (! $this->isConnected() || ! $eventId) {
            return;
        }

        try {
            $settings = GoogleCalendarSetting::instance();
            (new Calendar($this->buildClient()))
                ->events
                ->update($settings->calendar_id, $eventId, $this->buildEvent($rental));
        } catch (\Throwable $e) {
            Log::warning('GoogleCalendar: błąd aktualizacji eventu', [
                'rental_id' => $rental->id,
                'event_id'  => $eventId,
                'error'     => $e->getMessage(),
            ]);
        }
    }

    public function deleteEvent(string $eventId): void
    {
        if (! $this->isConnected()) {
            return;
        }

        try {
            $settings = GoogleCalendarSetting::instance();
            (new Calendar($this->buildClient()))
                ->events
                ->delete($settings->calendar_id, $eventId);
        } catch (\Throwable $e) {
            Log::warning('GoogleCalendar: błąd usuwania eventu', [
                'event_id' => $eventId,
                'error'    => $e->getMessage(),
            ]);
        }
    }

    private function buildClient(): Client
    {
        $settings = GoogleCalendarSetting::instance();

        $client = new Client();
        $client->setApplicationName(config('app.name'));
        $client->setScopes(self::SCOPES);
        $client->setClientId($settings->client_id ?? '');
        $client->setClientSecret($settings->client_secret ?? '');
        $client->setRedirectUri(route('google-calendar.callback'));

        if ($settings->access_token) {
            $client->setAccessToken(['access_token' => $settings->access_token]);

            $isExpired = $settings->token_expires_at && now()->isAfter($settings->token_expires_at);

            if ($isExpired && $settings->refresh_token) {
                $client->fetchAccessTokenWithRefreshToken($settings->refresh_token);
                $newToken = $client->getAccessToken();

                if (! empty($newToken['access_token'])) {
                    $this->storeTokens($settings, $newToken);
                }
            }
        }

        return $client;
    }

    private function buildEvent(Rental $rental): Event
    {
        $rentable = $rental->rentable;
        $resourceName = $rentable?->name ?? $rentable?->title ?? 'Rezerwacja';

        $statusLabels = [
            'pending'   => 'Oczekuje',
            'confirmed' => 'Potwierdzona',
            'paid'      => 'Opłacona',
            'cancelled' => 'Anulowana',
            'expired'   => 'Wygasła',
        ];

        // colorId wg Google Calendar API: 2=sage(green), 5=banana(yellow), 8=graphite, 9=blueberry
        $colors = [
            'pending'   => '5',
            'confirmed' => '2',
            'paid'      => '9',
            'cancelled' => '8',
            'expired'   => '8',
        ];

        $statusLabel = $statusLabels[$rental->status] ?? ucfirst($rental->status);
        $amount = $rental->total_amount > 0
            ? number_format($rental->total_amount / 100, 2, ',', ' ') . ' ' . $rental->currency
            : null;

        $descriptionParts = array_filter([
            'Klient: ' . ($rental->name ?? '—'),
            'Email: ' . ($rental->email ?? '—'),
            ! empty($rental->phone) ? 'Tel: ' . $rental->phone : null,
            'Status: ' . $statusLabel,
            $amount ? 'Kwota: ' . $amount : null,
            ! empty($rental->message) ? "\nWiadomość:\n" . $rental->message : null,
            "\nLink do rezerwacji:\n" . url('/admin/rentals/' . $rental->id . '/edit'),
        ]);

        $event = new Event([
            'summary'     => "[{$statusLabel}] {$resourceName}",
            'description' => implode("\n", $descriptionParts),
            'colorId'     => $colors[$rental->status] ?? '1',
        ]);

        // all-day event — end date w Google Calendar jest exclusive (dzień po ostatnim)
        $event->setStart(new EventDateTime(['date' => Carbon::parse($rental->start_date)->format('Y-m-d')]));
        $event->setEnd(new EventDateTime(['date' => Carbon::parse($rental->end_date)->addDay()->format('Y-m-d')]));

        return $event;
    }

    private function storeTokens(GoogleCalendarSetting $settings, array $token): void
    {
        $updateData = ['access_token' => $token['access_token']];

        if (! empty($token['refresh_token'])) {
            $updateData['refresh_token'] = $token['refresh_token'];
        }

        if (isset($token['expires_in'])) {
            $updateData['token_expires_at'] = now()->addSeconds((int) $token['expires_in']);
        }

        $settings->update($updateData);
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\GoogleCalendarService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class GoogleCalendarController extends Controller
{
    public function __construct(private readonly GoogleCalendarService $service) {}

    public function redirect(): RedirectResponse
    {
        return redirect($this->service->getAuthUrl());
    }

    public function callback(Request $request): RedirectResponse
    {
        $settingsUrl = route('filament.admin.pages.google-calendar-settings');

        if ($request->has('error')) {
            return redirect($settingsUrl)
                ->with('gcal_error', 'Autoryzacja odrzucona: ' . $request->input('error'));
        }

        try {
            $this->service->handleCallback($request->input('code'));
        } catch (\Throwable $e) {
            return redirect($settingsUrl)
                ->with('gcal_error', 'Błąd autoryzacji: ' . $e->getMessage());
        }

        return redirect($settingsUrl)
            ->with('gcal_success', 'Połączono z Google Calendar. Wybierz kalendarz z listy i zapisz.');
    }
}

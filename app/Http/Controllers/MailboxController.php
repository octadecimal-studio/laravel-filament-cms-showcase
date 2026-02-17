<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;

/**
 * Kontroler do przekierowania do Mailcow z automatycznym logowaniem.
 */
class MailboxController extends Controller
{
    /**
     * Przekierowanie do Mailcow z danymi użytkownika.
     */
    public function redirect(Request $request): View|RedirectResponse
    {
        // Sprawdź czy użytkownik jest zalogowany (przez Filament guard)
        $user = Auth::guard('web')->user();
        
        if (!$user) {
            // Przekieruj do logowania Filament
            return Redirect::to('/admin/login');
        }
        
        // Konwertuj email: octadecimal@example.com -> octadecimal-example.com
        $mailboxEmail = str_replace('@', '-', $user->email);
        
        // URL do Mailcow
        $mailcowUrl = 'https://203.0.113.10:8443';
        $mailboxUrl = $mailcowUrl . '/admin/mailbox';
        
        return view('mailbox.redirect', [
            'mailcowUrl' => $mailcowUrl,
            'mailboxUrl' => $mailboxUrl,
            'email' => $mailboxEmail,
        ]);
    }
}

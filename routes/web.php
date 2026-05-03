<?php

use App\Http\Controllers\MailboxController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/api-docs', function () {
    return view('swagger-ui');
})->name('api.docs');

// Mailbox redirect - autoryzacja sprawdzana w kontrolerze
Route::get('/admin/mailbox', [MailboxController::class, 'redirect'])->name('mailbox.redirect');

// P24 return URL → redirect do frontendu (sukces rezerwacji)
Route::get('/rezerwacja/status', function (\Illuminate\Http\Request $r) {
    $frontend = env('FRONTEND_URL', 'https://example-rental.test');
    $id = $r->query('id', '');
    return redirect()->away($frontend . '/rezerwacja/sukces?rental=' . urlencode($id), 302);
});

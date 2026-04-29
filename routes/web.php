<?php

use App\Http\Controllers\MailboxController;
use Illuminate\Support\Facades\Route;

// TST root: redirect to public staging frontend (Vercel)
// Klient testuje rezerwacje na froncie -> backend TST API
Route::get('/', function () {
    return redirect()->away('https://frontend-git-staging-piotradamczyk8s-projects.vercel.app', 302);
});

Route::get('/api-docs', function () {
    return view('swagger-ui');
})->name('api.docs');

// Mailbox redirect - autoryzacja sprawdzana w kontrolerze
Route::get('/admin/mailbox', [MailboxController::class, 'redirect'])->name('mailbox.redirect');

// P24 return URL: backend -> frontend Vercel
// P24 wysyla na app.url (backend) /rezerwacja/status?id=... -> przekieruj na frontend
Route::get('/rezerwacja/status', function (\Illuminate\Http\Request $r) {
    $frontend = env('FRONTEND_URL', 'https://motorent-demo-tst.vercel.app');
    $id = $r->query('id', '');
    return redirect()->away($frontend . '/rezerwacja/sukces?rental=' . urlencode($id), 302);
});

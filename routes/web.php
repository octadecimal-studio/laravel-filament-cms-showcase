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

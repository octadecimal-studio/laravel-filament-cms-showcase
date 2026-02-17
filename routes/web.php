<?php

use App\Http\Controllers\DebugController;
use App\Http\Controllers\MailboxController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/api-docs', function () {
    return view('swagger-ui');
})->name('api.docs');

// Debug route - USUNĄĆ NA PRODUKCJI
Route::get('/debug-auth', DebugController::class);

// Mailbox redirect - autoryzacja sprawdzana w kontrolerze
Route::get('/admin/mailbox', [MailboxController::class, 'redirect'])->name('mailbox.redirect');

// Widget routes - zarządzanie jobami analizy szablonów
Route::middleware(['auth'])->prefix('admin/widgets/template-jobs')->name('widgets.template-jobs.')->group(function () {
    Route::post('/{job}/delete', [\App\Http\Controllers\Widgets\TemplateJobsController::class, 'delete'])->name('delete');
});

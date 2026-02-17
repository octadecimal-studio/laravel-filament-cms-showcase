<?php

declare(strict_types=1);

namespace App\Session;

use Illuminate\Session\DatabaseSessionHandler as LaravelDatabaseSessionHandler;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Rozszerzony DatabaseSessionHandler, który zawsze zapisuje user_id
 * po regeneracji sesji przez AuthenticateSession middleware.
 */
class DatabaseSessionHandler extends LaravelDatabaseSessionHandler
{
    /**
     * {@inheritdoc}
     */
    public function write($sessionId, $data): bool
    {
        // Pobierz user_id z zalogowanego użytkownika
        $userId = Auth::id();

        // Wywołaj oryginalną metodę write
        $result = parent::write($sessionId, $data);

        // Jeśli użytkownik jest zalogowany, zaktualizuj user_id w sesji
        // To jest konieczne, ponieważ AuthenticateSession middleware regeneruje sesję
        // i nowa sesja nie ma automatycznie user_id
        if ($userId !== null && $result) {
            try {
                // Walidacja: sessionId musi być stringiem
                if (empty($sessionId) || ! is_string($sessionId)) {
                    Log::warning('DatabaseSessionHandler: Invalid session ID', [
                        'session_id' => $sessionId,
                        'type' => gettype($sessionId),
                    ]);
                    return $result;
                }
                
                // Rzutowanie na string dla bezpieczeństwa
                $sessionId = (string) $sessionId;
                $userId = (int) $userId;
                
                $this->connection->table($this->table)
                    ->where('id', $sessionId)
                    ->update(['user_id' => $userId]);
            } catch (\Exception $e) {
                // Loguj błąd, ale nie przerywaj zapisu sesji
                Log::warning('DatabaseSessionHandler: Failed to update user_id in session', [
                    'error' => $e->getMessage(),
                    'session_id' => $sessionId ?? 'null',
                    'type' => gettype($sessionId),
                ]);
            }
        }

        return $result;
    }
}

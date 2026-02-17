<?php /** @noinspection PhpUnhandledExceptionInspection */

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware aktualizujące user_id w sesji po zapisie.
 *
 * Rozwiązuje problem z AuthenticateSession middleware, który regeneruje sesję
 * i nowa sesja nie ma automatycznie user_id w kolumnie bazy danych.
 */
class UpdateSessionUserId
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Walidacja i normalizacja danych przed $next() - zapobiega "Array to string conversion"
        try {
            // Sprawdź i normalizuj session ID jeśli sesja jest już rozpoczęta
            if (session()->isStarted()) {
                $sessionId = session()->getId();

                // Jeśli session ID jest tablicą, spróbuj wyciągnąć wartość
                if (is_array($sessionId)) {
                    Log::warning('UpdateSessionUserId: Session ID is array, normalizing', [
                        'session_id' => $sessionId,
                    ]);
                    // Spróbuj wyciągnąć pierwszy element lub zserializować
                    $sessionId = is_array($sessionId) && !empty($sessionId)
                        ? (string) reset($sessionId)
                        : (string) $sessionId;

                    // Jeśli to nie zadziała, wygeneruj nowy ID
                    if (empty($sessionId) || !is_string($sessionId)) {
                        session()->regenerateId();
                        $sessionId = session()->getId();
                    }
                }

                // Upewnij się, że session ID to string
                if (!empty($sessionId) && !is_string($sessionId)) {
                    $sessionId = (string) $sessionId;
                }
            }

            // Sprawdź czy request nie zawiera tablic w miejscach gdzie oczekiwane są stringi
            // (np. w query params, headers)
            $this->normalizeRequestData($request);

        } catch (\Exception $e) {
            // Jeśli normalizacja się nie powiodła, loguj ale kontynuuj
            Log::warning('UpdateSessionUserId: Failed to normalize request data', [
                'error' => $e->getMessage(),
            ]);
        }

        try {
            $response = $next($request);
        } catch (\Throwable $e) {
            // Jeśli błąd występuje w $next(), loguj i rzuć dalej
            if (str_contains($e->getMessage(), 'Array to string conversion')) {
                Log::error('UpdateSessionUserId: Array to string conversion in $next()', [
                    'message' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                    'request_uri' => $request->getRequestUri(),
                    'session_id' => session()->isStarted() ? session()->getId() : 'not started',
                ]);
            }
            throw $e;
        }

        // Po odpowiedzi, zaktualizuj user_id w sesji jeśli użytkownik jest zalogowany
        // Używamy try-catch aby nie przerywać odpowiedzi w przypadku błędów
        try {
            if (! Auth::check()) {
                return $response;
            }

            // Sprawdź czy sesja jest dostępna
            if (! session()->isStarted()) {
                return $response;
            }

            $sessionId = session()->getId();

            // Walidacja: session()->getId() musi zwracać string
            if (empty($sessionId) || ! is_string($sessionId)) {
                Log::warning('UpdateSessionUserId: Invalid session ID', [
                    'session_id' => $sessionId,
                    'type' => gettype($sessionId),
                ]);
                return $response;
            }

            $userId = Auth::id();

            // Walidacja: user_id musi być int lub null
            if ($userId === null) {
                return $response;
            }

            // Upewnij się, że userId jest integerem
            $userId = (int) $userId;

            // Pobierz nazwę tabeli sesji - upewnij się, że to string
            $sessionTable = config('session.table', 'sessions');
            if (! is_string($sessionTable) || empty($sessionTable)) {
                $sessionTable = 'sessions';
            }

            // Upewnij się, że sessionId to string przed użyciem w where()
            $sessionId = (string) $sessionId;

            DB::table($sessionTable)
                ->where('id', $sessionId)
                ->update(['user_id' => $userId]);

        } catch (\Exception $e) {
            // Loguj błąd, ale nie przerywaj odpowiedzi
            Log::warning('UpdateSessionUserId: Failed to update user_id in session', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }

        return $response;
    }

    /**
     * Normalizuj dane w request, aby uniknąć "Array to string conversion".
     */
    private function normalizeRequestData(Request $request): void
    {
        // Sprawdź query params - jeśli są tablice, zostaw je (to może być zamierzone)
        // Ale upewnij się, że nie próbujemy ich użyć jako stringów

        // Sprawdź headers - niektóre mogą być tablicami
        $headers = $request->headers->all();
        foreach ($headers as $key => $value) {
            // Jeśli header jest tablicą z jednym elementem, wyciągnij go
            if (is_array($value) && count($value) === 1) {
                $request->headers->set($key, (string) reset($value));
            }
        }
    }
}

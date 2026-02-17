<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DebugController extends Controller
{
    /**
     * Debug logowania.
     */
    public function __invoke(Request $request)
    {
        $action = $request->query('action', 'status');
        
        if ($action === 'login') {
            $user = User::where('email', 'admin@demo-studio.local')->first();
            
            // Zaloguj użytkownika z regeneracją sesji
            Auth::login($user, true);
            
            // Regeneruj session ID dla bezpieczeństwa
            session()->regenerate();
            
            // Zapisz sesję
            session()->save();
            
            return response()->json([
                'action' => 'login',
                'logged_in' => Auth::check(),
                'user_id' => Auth::id(),
                'session_id' => session()->getId(),
                'session_payload_keys' => array_keys(session()->all()),
            ]);
        }
        
        // Status sesji
        $session = DB::table('sessions')->first();
        
        return response()->json([
            'action' => 'status',
            'auth_check' => Auth::check(),
            'auth_id' => Auth::id(),
            'session_id' => session()->getId(),
            'db_session_count' => DB::table('sessions')->count(),
            'db_session_user_id' => $session?->user_id,
            'request_host' => $request->getHost(),
            'request_port' => $request->getPort(),
        ]);
    }
}

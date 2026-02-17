<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Site;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware identyfikujący stronę po slug.
 *
 * Ładuje Site z bazy i przekazuje do kontrolera.
 */
final class IdentifySite
{
    /**
     * Obsługuje request.
     *
     * @param Request $request
     * @param Closure $next
     * @return Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        $slug = $request->route('slug');

        if ($slug === null) {
            return response()->json([
                'error' => 'Site slug is required',
            ], 400);
        }

        $site = Site::query()
            ->where('slug', $slug)
            ->where('status', '!=', 'suspended')
            ->first();

        if ($site === null) {
            return response()->json([
                'error' => 'Site not found',
                'slug' => $slug,
            ], 404);
        }

        // Zastąp slug obiektem Site w route parameters
        $request->route()->setParameter('slug', $site);
        $request->route()->setParameter('site', $site);

        // Zapisz w request dla wygody
        $request->attributes->set('site', $site);

        return $next($request);
    }
}

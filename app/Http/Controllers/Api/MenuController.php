<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Datlechin\FilamentMenuBuilder\Models\Menu;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controller do obsługi API dla menu.
 */
class MenuController extends Controller
{
    /**
     * Pobierz menu dla danej lokacji.
     *
     * @param  Request  $request
     * @param  string  $location
     * @return JsonResponse
     */
    public function getByLocation(Request $request, string $location): JsonResponse
    {
        $menu = Menu::location($location);

        if (!$menu) {
            return response()->json([
                'data' => null,
                'message' => "Menu dla lokacji '{$location}' nie zostało znalezione.",
            ], 404);
        }

        return response()->json([
            'data' => [
                'id' => $menu->id,
                'name' => $menu->name,
                'is_visible' => $menu->is_visible,
                'items' => $this->formatMenuItems($menu->menuItems),
            ],
        ]);
    }

    /**
     * Formatuj elementy menu do struktury zagnieżdżonej.
     *
     * @param  \Illuminate\Database\Eloquent\Collection  $items
     * @return array<int, array<string, mixed>>
     */
    private function formatMenuItems($items): array
    {
        return $items->map(function ($item) {
            return [
                'id' => $item->id,
                'title' => $item->title,
                'url' => $item->url,
                'target' => $item->target?->value ?? '_self',
                'order' => $item->order,
                'children' => $item->children->isNotEmpty() ? $this->formatMenuItems($item->children) : [],
            ];
        })->toArray();
    }
}

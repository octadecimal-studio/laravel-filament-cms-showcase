@php
    use Datlechin\FilamentMenuBuilder\Models\Menu;
    
    $menu = Menu::location($location);
    
    // Upewnij się, że menuItems są załadowane
    if ($menu) {
        $menu->load('menuItems.children');
    }
@endphp

@if($menu && $menu->is_visible && $menu->menuItems->isNotEmpty())
    <nav class="menu menu-{{ $location }}" role="navigation" aria-label="{{ $menu->name }}">
        <ul class="menu-list" style="display: flex; gap: 1rem; list-style: none; padding: 0; margin: 0;">
            @foreach($menu->menuItems as $item)
                @include('components.menu-item', ['item' => $item])
            @endforeach
        </ul>
    </nav>
@endif

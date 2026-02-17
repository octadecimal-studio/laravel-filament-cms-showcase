@php
    $hasChildren = $item->children && $item->children->isNotEmpty();
    $target = $item->target?->value ?? '_self';
    $url = $item->url ?? '#';
@endphp

<li class="menu-item {{ $hasChildren ? 'has-children' : '' }}" style="margin: 0; padding: 0;">
    <a 
        href="{{ $url }}" 
        target="{{ $target }}"
        class="menu-link"
        style="text-decoration: none; color: inherit; padding: 0.5rem 1rem; display: block;"
        @if($target === '_blank') rel="noopener noreferrer" @endif
    >
        {{ $item->title }}
    </a>
    
    @if($hasChildren)
        <ul class="menu-submenu" style="list-style: none; padding: 0; margin: 0; padding-left: 1rem;">
            @foreach($item->children as $child)
                @include('components.menu-item', ['item' => $child])
            @endforeach
        </ul>
    @endif
</li>

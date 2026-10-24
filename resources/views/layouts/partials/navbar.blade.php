<aside class="sidebar" id="navigation" aria-label="Navegación principal">
    <div class="sidebar-header">
        <a class="brand" href="{{ route('dashboard') }}" aria-label="BUKO, inicio"><span class="brand-symbol" aria-hidden="true"></span><span class="brand-name">BUKO</span></a>
        <button class="button button-quiet icon-button sidebar-mobile-close" type="button" data-sidebar-close aria-label="Cerrar menú"><x-icon name="x" /></button>
    </div>
    <span class="nav-caption">Espacio de trabajo</span>
    <nav class="nav-links">
        @foreach($menuItems as $group)
            <div class="nav-group">
                <button class="nav-group-trigger" type="button" data-nav-disclosure aria-label="{{ $group->name }}" title="{{ $group->name }}" aria-expanded="{{ $group->active ? 'true' : 'false' }}" aria-controls="nav-group-{{ $group->id }}">
                    <x-icon :name="$group->icon" />
                    <span class="nav-label">{{ $group->name }}</span>
                    <x-icon name="chevron-down" size="15" class="nav-chevron" />
                </button>
                <div class="nav-group-panel" id="nav-group-{{ $group->id }}" @if(!$group->active) hidden @endif>
                    @foreach($group->children as $item)
                        <a href="{{ $item->url }}" @class(['nav-link', 'is-current' => $item->active]) @if($item->active) aria-current="page" @endif title="{{ $item->name }}">
                            <x-icon :name="$item->icon" size="18" />
                            <span class="nav-label">{{ $item->name }}</span>
                        </a>
                    @endforeach
                </div>
            </div>
        @endforeach
    </nav>
    <div class="sidebar-context"><x-icon name="building" /><div><span class="nav-caption">{{ auth()->user()->is_super_admin ? 'Administración del sistema' : 'Empresa' }}</span><strong>{{ auth()->user()->company?->name ?? 'BUKO' }}</strong></div></div>
</aside>

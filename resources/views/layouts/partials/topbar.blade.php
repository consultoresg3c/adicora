<header class="topbar">
    <div class="topbar-start">
        <button type="button" class="button button-quiet icon-button" data-sidebar-toggle aria-controls="navigation" aria-expanded="true" aria-label="Contraer menú" title="Contraer menú">
            <x-icon name="layout-sidebar-left-collapse" />
        </button>
        <span class="topbar-context">Gestión empresarial</span>
    </div>
    <div class="account-menu">
        <button type="button" class="button button-quiet icon-button" data-theme-toggle aria-label="Cambiar tema" title="Cambiar tema">
            <x-icon name="sun" class="theme-icon-light" />
            <x-icon name="moon" class="theme-icon-dark" />
        </button>
        <div class="account-identity">
            <span class="account-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}</span>
            <span><strong>{{ auth()->user()->name }}</strong><small>{{ auth()->user()->company?->name ?? 'Administración del sistema' }}</small></span>
        </div>
        <form action="{{ route('logout') }}" method="post">
            @csrf
            <button class="button button-quiet icon-button" type="submit" aria-label="Cerrar sesión" title="Cerrar sesión"><x-icon name="logout" /></button>
        </form>
    </div>
</header>

<!doctype html>
<html lang="es">
<head>
    @include('layouts.partials.head')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @hasSection('module')
        @vite(trim($__env->yieldContent('module')))
    @endif
</head>
<body>
    <a class="skip-link" href="#content">Ir al contenido</a>
    <div class="app-shell" data-app-shell>
        @include('layouts.partials.navbar')
        <button class="sidebar-backdrop" type="button" data-sidebar-close aria-label="Cerrar menú" tabindex="-1" hidden></button>
        <div class="app-main">
            @include('layouts.partials.topbar')
            <main id="content" class="page-content" tabindex="-1">
                @include('layouts.partials.header')
                @if(session('success'))
                    <p class="notice" role="status">
                        {{ session('success') }}
                        @if(session('history_url')) <a href="{{ session('history_url') }}">Ver historial</a> @endif
                    </p>
                @endif
                @yield('content')
            </main>
            @include('layouts.partials.footer')
        </div>
    </div>
    <x-record-dialog id="record-confirm-dialog" title="Confirmar eliminación" />
    <div data-record-print-root></div>
</body>
</html>

<!doctype html>
<html lang="es">
<head>
    @include('layouts.partials.head')
    @vite(['resources/css/app.css', 'resources/js/modules/auth/login.js'])
</head>
<body class="guest-body">
    @yield('content')
</body>
</html>

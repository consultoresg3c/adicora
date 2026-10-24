<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="color-scheme" content="light dark">
<script>
    try {
        const preference = localStorage.getItem('buko-theme');
        document.documentElement.dataset.theme = ['light', 'dark'].includes(preference)
            ? preference : (matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
    } catch {
        document.documentElement.dataset.theme = matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
    }
</script>
<title>@yield('title', 'Inicio') | BUKO</title>
<link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">

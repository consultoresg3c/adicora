@extends('layouts.guest')
@section('title', 'Iniciar sesión')
@section('content')
<main class="login-layout">
    <section class="login-form-side" aria-labelledby="login-title">
        <header class="login-header"><a class="brand login-brand" href="{{ route('login') }}" aria-label="BUKO, inicio"><span class="brand-symbol" aria-hidden="true"></span> BUKO</a><button type="button" class="button button-quiet icon-button" data-theme-toggle aria-label="Cambiar tema"><x-icon name="sun" class="theme-icon-light" /><x-icon name="moon" class="theme-icon-dark" /></button></header>
        <div class="login-form-container">
            <p class="login-kicker">Tu espacio de trabajo</p>
            <h1 id="login-title">Iniciar sesión</h1>
            <p class="login-description">Ingresa con tu cuenta para continuar.</p>
            <form action="{{ route('login.store') }}" method="post" data-async-form novalidate>
                @csrf
                <x-field name="email" label="Correo electrónico" type="email" required autocomplete="username" placeholder="nombre@empresa.com" maxlength="255" />
                <div class="password-field">
                    <x-field name="password" label="Contraseña" type="password" required autocomplete="current-password" />
                    <button type="button" class="password-toggle icon-button" data-password-toggle aria-controls="password" aria-pressed="false" aria-label="Mostrar contraseña" title="Mostrar contraseña"><x-icon name="eye" class="password-icon-show" /><x-icon name="eye-off" class="password-icon-hide" /></button>
                </div>
                <label class="checkbox-label remember-label"><input type="checkbox" name="remember" value="1"> Mantener mi sesión iniciada</label>
                <div class="form-feedback" data-form-feedback role="alert" tabindex="-1">@if($errors->any())Revisa tus datos para continuar.@endif</div>
                <button type="submit" class="button button-primary login-submit" data-submit><span data-submit-label>Ingresar</span><x-icon name="chevron-right" size="20" /></button>
            </form>
            <p class="login-help">¿Necesitas una cuenta? Contacta al administrador de tu empresa.</p>
        </div>
        <footer class="login-footer">
            @if(app()->isLocal() && config('buko.show_local_credentials'))
                <details class="local-credentials"><summary>Acceso inicial de desarrollo</summary><p>Correo: <strong>{{ config('buko.initial_admin.email') }}</strong></p><p>Contraseña: <code>{{ config('buko.initial_admin.password') }}</code></p><p>Disponible después de ejecutar migraciones y seeders.</p></details>
            @else
                <span>BUKO</span><span>Gestión empresarial</span>
            @endif
        </footer>
    </section>
    <aside class="login-art" aria-hidden="true"><div class="sculpture"><div class="sculpture-band band-one"></div><div class="sculpture-band band-two"></div><div class="sculpture-band band-three"></div><div class="sculpture-band band-four"></div><div class="sculpture-band band-five"></div></div><div class="art-palette"><i></i><i></i><i></i><i></i><i></i><i></i></div></aside>
</main>
@endsection

<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport"
          content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>{{$title}}</title>

    <meta name="theme-color" content="#b45a35">
    <link rel="manifest" href="/manifest.webmanifest">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="{{ config('app.name') }}">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="icon" href="/favicon-96x96.png" type="image/png" sizes="96x96">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
<div class="layout">
    <header class="site-header">
        <a href="/" class="site-header__logo">{{ config('app.name', 'Klog') }}</a>
        <nav class="site-header__nav">
            <a href="{{ route('settings') }}" class="site-header__link">Settings</a>
            <a href="{{ route('memories.create') }}" class="site-header__link--primary">+ New Memory</a>
        </nav>
    </header>

    <main class="layout__main">
        {{$slot}}
    </main>
</div>
</body>
</html>

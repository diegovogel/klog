<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport"
          content="width=device-width, initial-scale=1">
    <title>{{$title}}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
<div class="layout">
    <header class="site-header">
        <a href="/" class="site-header__logo">{{ config('app.name', 'Klog') }}</a>
        <nav class="site-header__nav">
            <a href="{{ route('two-factor.settings') }}" class="site-header__link">Settings</a>
            <a href="{{ route('memories.create') }}" class="site-header__link--primary">+ New Memory</a>
        </nav>
    </header>

    <main class="layout__main">
        {{$slot}}
    </main>
</div>
</body>
</html>

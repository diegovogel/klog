<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport"
          content="width=device-width, initial-scale=1">
    <meta name="color-scheme"
          content="light dark">
    <title>{{$title}}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
<header class="site-header">
    <a href="/"><h1 class="site-header__title">{{config('app.name', 'Klog')}}</h1></a>
    <nav class="site-header__nav">
        <a href="{{ route('two-factor.settings') }}">Settings</a>
    </nav>
</header>

<main class="container">
    <h1 class="page-title">{{$pageTitle}}</h1>

    {{$slot}}
</main>
</body>
</html>

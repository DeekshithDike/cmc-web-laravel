<!doctype html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title') — {{ config('citymax.name') }}</title>
    <link rel="icon" type="image/png" href="{{ asset('branding/favicon-32.png') }}">
    <script>
      (function () {
        const saved = localStorage.getItem("cmc-theme");
        const prefersDark = window.matchMedia("(prefers-color-scheme: dark)").matches;
        if ((saved ? saved === "dark" : prefersDark)) document.documentElement.classList.add("dark");
      })();
    </script>
    <link href="{{ asset('customer-assets/css/index.css') }}" rel="stylesheet">
</head>
<body class="bg-bg text-text dark:text-text font-sans antialiased">
<main class="min-h-dvh flex items-center justify-center px-4 py-10">
    <div class="w-full max-w-md">
        <div class="bg-surface border border-border rounded-3xl shadow-xl shadow-black/5 p-6 sm:p-8">
            <a class="flex justify-center mb-6" href="{{ route('landing') }}">
                <img src="{{ asset('customer-assets/images/logo.png') }}" alt="{{ config('citymax.name') }}" class="h-10 object-contain">
            </a>
            @yield('content')
        </div>
        <div class="flex flex-wrap items-center justify-center gap-x-4 gap-y-1.5 text-[11px] text-muted mt-5">
            <span class="inline-flex items-center gap-1"><i class="ph ph-shield-check text-success"></i>Secure sign-in</span>
            <span class="inline-flex items-center gap-1"><i class="ph ph-lock-key text-success"></i>{{ config('citymax.tagline') }}</span>
        </div>
        <p class="text-center text-[11px] text-faint mt-4">© {{ date('Y') }} {{ config('citymax.name') }}</p>
    </div>
</main>
<script src="{{ asset('customer-assets/js/app.js') }}"></script>
@stack('scripts')
</body>
</html>

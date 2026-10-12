<!DOCTYPE html>
{{-- Admin panel pages (admin/*) use the admin surface; keep in sync with surfaceFor() in resources/js/lib/page-resolver.ts --}}
@php($surface = str_starts_with($page['component'], 'admin/') ? 'admin' : null)
{{-- Accent preset from APP_ACCENT; the default needs no attribute. --}}
@php($accent = \App\Enums\AccentColor::tryFrom((string) config('app.accent')) ?? \App\Enums\AccentColor::Default)
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app(\App\Services\Localization\LocalizationConfig::class)->getDirection(app()->getLocale()) }}" @class(['dark' => ($appearance ?? 'system') == 'dark']) @if($surface) data-surface="{{ $surface }}" @endif @if($accent !== \App\Enums\AccentColor::Default) data-accent="{{ $accent->value }}" @endif>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        {{-- Inline script to detect system dark mode preference and apply it immediately --}}
        <script>
            (function() {
                const appearance = '{{ $appearance ?? "system" }}';

                if (appearance === 'system') {
                    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

                    if (prefersDark) {
                        document.documentElement.classList.add('dark');
                    }
                }
            })();
        </script>

        {{-- Pre-CSS background to avoid a flash; keep in sync with --background in app.css --}}
        <style>
            html {
                background-color: oklch(0.985 0.005 85);
            }

            html.dark {
                background-color: oklch(0.14 0.005 85);
            }

            html[data-surface="admin"] {
                background-color: oklch(0.984 0.003 255);
            }

            html.dark[data-surface="admin"] {
                background-color: oklch(0.165 0.006 260);
            }
        </style>

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

        @fonts

        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/app.tsx', "resources/js/pages/{$page['component']}.tsx"])
        <x-inertia::head>
            <title>{{ config('app.name', 'Laravel') }}</title>
        </x-inertia::head>
    </head>
    <body class="font-sans antialiased">
        <x-inertia::app />
    </body>
</html>

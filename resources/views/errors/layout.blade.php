<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-[#1A1A1A]">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') | {{ config('cms.name') }}</title>
    <style>
        body {
            font-family: 'Google Sans Code', monospace;
            background-color: #1A1A1A;
            color: #FFFFFF;
        }
        .cyber-border {
            border: 1px solid rgba(0, 255, 153, 0.2);
            box-shadow: 0 0 30px rgba(0, 255, 153, 0.05);
        }
        .btn-cyber {
            background-color: transparent;
            border: 1px solid #00FF99;
            color: #00FF99;
            font-family: 'Google Sans Code', monospace;
            font-weight: 700;
            padding: 0.5rem 1.25rem;
            border-radius: 0.375rem;
            transition: all 0.2s ease;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.05em;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        .btn-cyber:hover {
            background-color: #00FF99;
            color: #1A1A1A;
            box-shadow: 0 0 15px rgba(0, 255, 153, 0.4);
        }
        .cursor-blink {
            color: #00FF99;
            animation: blink 1s step-end infinite;
        }
        @keyframes blink {
            0%, 100% { opacity: 1; }
            50% { opacity: 0; }
        }
    </style>
    @vite(cms_asset_entries())
</head>
<body class="h-full flex flex-col justify-between p-6">
    <header class="flex items-center justify-between">
        <a href="/" class="text-xl font-bold tracking-tight text-white flex items-center gap-1 font-mono">
            <span>&gt;</span><span class="cursor-blink">_</span><span>{{ config('cms.name') }}</span>
        </a>
        <span class="text-xs font-mono text-[#00FF99] border border-[#00FF99]/40 bg-[#00FF99]/10 px-2 py-0.5 rounded font-bold">
            CORE
        </span>
    </header>

    <main class="max-w-xl mx-auto w-full text-center my-auto py-12">
        <div class="bg-[#141414] p-8 sm:p-12 rounded-xl cyber-border text-center">
            <div class="inline-block mb-4 font-mono text-xs text-[#00FF99] uppercase tracking-widest px-3 py-1 bg-[#00FF99]/10 rounded border border-[#00FF99]/20">
                Error de Sistema
            </div>
            
            <h1 class="text-6xl sm:text-7xl font-bold font-mono text-white mb-4">
                @yield('code')
            </h1>
            
            <p class="text-lg text-gray-300 font-mono mb-6">
                @yield('message')
            </p>

            <div class="pt-4 flex flex-wrap items-center justify-center gap-4">
                <a href="/" class="btn-cyber">
                    <span>&larr; Volver a la portada</span>
                </a>
                <a href="{{ route('admin.dashboard') }}" class="px-4 py-2 bg-[#1A1A1A] hover:bg-gray-800 border border-gray-700 text-gray-300 hover:text-white rounded text-xs font-mono transition-colors">
                    <span>Acceso al Backoffice &rarr;</span>
                </a>
            </div>
        </div>
    </main>

    <footer class="text-center font-mono text-xs text-gray-600">
        <p>{{ config('cms.name') }} &middot; {{ config('cms.tagline') ?: __('admin.brand.tagline_default') }}</p>
    </footer>
</body>
</html>

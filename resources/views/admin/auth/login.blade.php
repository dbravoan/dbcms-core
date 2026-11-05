<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-[#1A1A1A]">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('admin.auth.backoffice_access_title') }} | &gt;_{{ config('cms.name') }}</title>
    <style>
        body {
            font-family: 'Google Sans Code', monospace;
            background-color: #1A1A1A;
            color: #FFFFFF;
        }
        .btn-neon {
            padding: 10px 20px;
            background-color: transparent;
            border: 1px solid #00FF99;
            color: #00FF99;
            font-family: 'Google Sans Code', monospace;
            font-weight: 700;
            font-size: 0.85rem;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.25s ease;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        .btn-neon:hover {
            background-color: #00FF99;
            color: #1A1A1A;
            box-shadow: 0 0 15px rgba(0, 255, 153, 0.4);
        }
        .cyber-card {
            background-color: #141414;
            border: 1px solid rgba(0, 255, 153, 0.2);
            box-shadow: 0 10px 30px -10px rgba(0, 0, 0, 0.8);
        }
        .cursor-blink {
            color: #00FF99;
            display: inline-block;
            animation: blink 1s step-end infinite;
        }
        @keyframes blink {
            0%, 100% { opacity: 1; }
            50% { opacity: 0; }
        }
    </style>
    @vite(cms_asset_entries())
</head>
<body class="h-full flex flex-col justify-center py-12 sm:px-6 lg:px-8">
    <div class="sm:mx-auto sm:w-full sm:max-w-md text-center">
        <h2 class="text-3xl font-extrabold text-white tracking-wider flex items-center justify-center gap-2">
            <span>&gt;</span><span class="cursor-blink">_</span><span>{{ config('cms.name') }}</span>
            <span class="text-[10px] font-mono text-[#00FF99] border border-[#00FF99]/40 bg-[#00FF99]/10 px-1.5 py-0.5 rounded font-bold ml-1">
                CORE
            </span>
        </h2>
        <p class="mt-2 text-xs text-gray-400 font-mono">
            {{ __('admin.auth.tagline') }}
        </p>
    </div>

    <div class="mt-8 sm:mx-auto sm:w-full sm:max-w-md">
        <div class="cyber-card py-8 px-6 shadow sm:rounded-xl sm:px-10">
            @if(session('error'))
                <div class="mb-4 p-3 bg-red-900/30 border border-red-800 text-red-400 rounded-lg text-xs font-mono">
                    {{ session('error') }}
                </div>
            @endif

            @if(session('success'))
                <div class="mb-4 p-3 bg-[#00FF99]/10 border border-[#00FF99]/30 text-[#00FF99] rounded-lg text-xs font-mono">
                    {{ session('success') }}
                </div>
            @endif

            <form method="POST" action="{{ route('admin.login.submit') }}" class="space-y-4 font-mono">
                @csrf
                <div>
                    <label class="block text-xs font-bold text-gray-400 uppercase">{{ __('admin.auth.email') }}</label>
                    <input type="email" name="email" value="{{ old('email') }}" required autofocus
                           class="mt-1 block w-full bg-[#1A1A1A] border border-gray-700 focus:border-[#00FF99] focus:ring-1 focus:ring-[#00FF99] rounded-lg px-4 py-2.5 text-sm text-white transition-colors">
                    @error('email')
                        <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-400 uppercase">{{ __('admin.auth.password') }}</label>
                    <input type="password" name="password" required
                           class="mt-1 block w-full bg-[#1A1A1A] border border-gray-700 focus:border-[#00FF99] focus:ring-1 focus:ring-[#00FF99] rounded-lg px-4 py-2.5 text-sm text-white transition-colors">
                    @error('password')
                        <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <div class="flex items-center justify-between">
                    <label class="flex items-center text-xs text-gray-400">
                        <input type="checkbox" name="remember" class="rounded bg-[#1A1A1A] border-gray-700 text-[#00FF99] focus:ring-[#00FF99]" checked>
                        <span class="ml-2">{{ __('admin.auth.remember') }}</span>
                    </label>
                </div>

                <div class="pt-2">
                    <button type="submit" class="btn-neon w-full">
                        <span>{{ __('admin.auth.submit') }}</span>
                        <span>&rarr;</span>
                    </button>
                </div>
            </form>

            <x-admin.demo-personas :personas="$demoPersonas ?? []" />
        </div>
    </div>
</body>
</html>

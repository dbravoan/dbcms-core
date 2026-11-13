@extends('install.layout')

@section('title', 'Paso 4: Configurar tu sitio')

@section('content')
<div data-install-wizard x-data="installationWizard({
    errors: @js($errors->keys()),
    adminSlug: @js(old('admin_path', $defaultAdminPath)),
    siteName: @js(old('site_name', config('cms.name', 'CMS'))),
    appUrl: @js(old('app_url', $defaultAppUrl)),
    brand: @js(old('brand', $brandChoice['selected'])),
    frontTheme: @js(old('front_theme', $themeChoice['selected']))
})" class="space-y-6">
    <!-- Header -->
    <div>
        <h2 class="text-xl font-bold text-white flex items-center gap-2">
            <span class="text-[#00FF99]">&gt;</span> Configura tu sitio, paso a paso
        </h2>
        <p class="text-xs text-gray-400 mt-1">
            Primero el sitio y tu acceso. Después, los datos del titular y la privacidad. Puedes volver sin perder lo escrito.
        </p>
    </div>

    @if($errors->any())
        <div class="bg-red-500/10 border border-red-500/40 text-red-400 p-4 rounded-xl text-xs">
            <ul class="list-disc pl-4 space-y-1">
                @foreach($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <nav aria-label="Configuración del sitio" x-cloak class="grid grid-cols-2 sm:grid-cols-4 gap-2">
        @foreach(['Sitio', 'Administrador', 'Titular', 'Privacidad'] as $label)
            <button type="button" @click="go({{ $loop->index }})" :disabled="visited < {{ $loop->index }}"
                    :aria-current="step === {{ $loop->index }} ? 'step' : null"
                    :class="step === {{ $loop->index }} ? 'step-pill-active' : ''"
                    class="border border-gray-700 rounded-lg p-3 text-xs text-left disabled:opacity-50">
                <span class="text-gray-400">{{ $loop->iteration }}.</span> {{ $label }}
            </button>
        @endforeach
    </nav>
    <form id="install-form" x-ref="form" method="POST" action="{{ route('install.store-admin') }}" @submit="submit($event)" class="space-y-4" :data-install-current-step="steps[step].id">
        @csrf
        <section data-install-step="site" x-show="step === 0" class="space-y-4" aria-labelledby="install-site-heading">
            <h3 id="install-site-heading" tabindex="-1" class="text-lg font-bold text-white">Tu sitio</h3>

        <!-- Brand & Front Theme Card -->
        <div class="p-5 bg-[#121212] rounded-xl border border-gray-800 space-y-4">
            <div class="text-xs font-bold text-[#00FF99] uppercase tracking-wider flex items-center gap-1.5">
                <i aria-hidden="true" class="fa-solid fa-palette"></i>
                <span>Marca del CMS & Tema Público</span>
            </div>

            <p class="text-[11px] text-gray-400 leading-relaxed">
                La marca define el aspecto del backoffice (colores, logotipo y tipografía). Puedes cambiarla más adelante desde
                <strong class="text-gray-300">Temas</strong> o con <code class="text-gray-300">CMS_BRAND</code> en el archivo <code class="text-gray-300">.env</code>.
            </p>

            @if($brandChoice['mode'] === 'multiple')
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    @foreach($installBrands as $brand)
                        <label class="cursor-pointer">
                            <input type="radio" name="brand" value="{{ $brand['identifier'] }}" class="peer sr-only"
                                   x-model="brand" @checked(old('brand', $brandChoice['selected']) === $brand['identifier'])>
                            <div class="h-full rounded-lg border border-gray-800 bg-[#181818] p-3 space-y-2 transition-colors peer-checked:border-[#00FF99] peer-checked:bg-[#00FF99]/5 peer-focus-visible:ring-1 peer-focus-visible:ring-[#00FF99]">
                                <div class="flex items-center gap-2">
                                    <span class="w-3 h-3 rounded-full shrink-0 border border-white/10" style="background-color: {{ $brand['accent'] }}"></span>
                                    @if(!empty($brand['icon']))
                                        <img src="{{ $brand['icon'] }}" alt="" class="w-5 h-5 rounded object-cover shrink-0">
                                    @endif
                                    <span class="text-xs font-bold text-white truncate">{{ $brand['name'] }}</span>
                                </div>
                                <p class="text-[10px] text-gray-500 leading-snug">
                                    {{ \Illuminate\Support\Str::limit($brand['theme_name'], 46) }}
                                </p>
                            </div>
                        </label>
                    @endforeach
                </div>
            @elseif($brandChoice['mode'] === 'single')
                @php $singleBrand = $installBrands[0]; @endphp
                <input type="hidden" name="brand" value="{{ $singleBrand['identifier'] }}">
                <div class="flex items-center gap-3 rounded-lg border border-gray-800 bg-[#181818] p-3">
                    <span class="w-3 h-3 rounded-full shrink-0 border border-white/10" style="background-color: {{ $singleBrand['accent'] }}"></span>
                    @if(!empty($singleBrand['icon']))
                        <img src="{{ $singleBrand['icon'] }}" alt="" class="w-6 h-6 rounded object-cover shrink-0">
                    @endif
                    <div class="min-w-0">
                        <p class="text-xs font-bold text-white truncate">{{ $singleBrand['name'] }}</p>
                        <p class="text-[10px] text-gray-500 leading-snug">{{ $singleBrand['theme_name'] }}</p>
                    </div>
                    <span class="ml-auto text-[10px] font-bold text-[#00FF99] uppercase tracking-wider shrink-0">Única marca</span>
                </div>
            @else
                <p class="text-[11px] text-amber-400">
                    <i aria-hidden="true" class="fa-solid fa-triangle-exclamation mr-1"></i>
                    No se ha detectado ninguna marca en disco; se usará la configuración por defecto.
                </p>
            @endif

            @if($themeChoice['mode'] === 'multiple')
                <div class="space-y-1 pt-1">
                    <label class="block text-xs font-bold text-gray-400 uppercase" for="install_front_theme">Tema público (frontoffice)</label>
                    <select id="install_front_theme" name="front_theme" x-model="frontTheme"
                            class="w-full bg-[#181818] border border-gray-700 focus:border-[#00FF99] focus:ring-1 focus:ring-[#00FF99] rounded-lg px-4 py-2 text-white text-sm">
                        @foreach($frontThemes as $theme)
                            <option value="{{ $theme['identifier'] }}" @selected(old('front_theme', $themeChoice['selected']) === $theme['identifier'])>
                                {{ $theme['name'] }} ({{ $theme['identifier'] }})
                            </option>
                        @endforeach
                    </select>
                </div>
            @elseif($themeChoice['mode'] === 'single')
                @php $singleTheme = $frontThemes[0]; @endphp
                <input type="hidden" name="front_theme" value="{{ $singleTheme['identifier'] }}">
                <p class="text-[11px] text-gray-400 pt-1">
                    Tema público: <span class="text-gray-200 font-bold">{{ $singleTheme['name'] }}</span>
                    <span class="text-gray-600">({{ $singleTheme['identifier'] }})</span><span class="text-[10px] text-gray-500"> — único disponible</span>
                </p>
            @endif
        </div>

        <!-- Site & Admin Slug Card -->
        <div class="p-5 bg-[#121212] rounded-xl border border-gray-800 space-y-4">
            <div class="text-xs font-bold text-[#00FF99] uppercase tracking-wider flex items-center gap-1.5">
                <span><i aria-hidden="true" class="fa-solid fa-shield-halved"></i></span>
                <span>Configuración de Identidad y URL del Panel</span>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="space-y-1">
                    <label class="block text-xs font-bold text-gray-400 uppercase" for="install-site-name">Nombre del Sitio Web</label>
                    <input type="text" id="install-site-name" name="site_name" x-model="siteName" required
                           class="w-full bg-[#181818] border border-gray-700 focus:border-[#00FF99] focus:ring-1 focus:ring-[#00FF99] rounded-lg px-4 py-2 text-white text-sm">
                </div>

                <div class="space-y-1">
                    <label class="block text-xs font-bold text-gray-400 uppercase" for="install-admin-path">
                        Ruta del Panel (Slug) <span class="text-[#00FF99]">*</span>
                    </label>
                    <div class="flex items-center">
                        <span class="bg-[#181818] border border-r-0 border-gray-700 rounded-l-lg px-3 py-2 text-gray-500 text-sm">/</span>
                        <input type="text" id="install-admin-path" name="admin_path" x-model="adminSlug" required pattern="[a-zA-Z0-9_\-]+"
                               placeholder="admin"
                               class="w-full bg-[#181818] border border-gray-700 focus:border-[#00FF99] focus:ring-1 focus:ring-[#00FF99] rounded-r-lg px-3 py-2 text-[#00FF99] text-sm font-bold">
                    </div>
                    <p class="text-[11px] text-gray-500 mt-1">
                        Ejemplo de uso: <code class="text-gray-300" x-text="'https://tudominio.com/' + adminSlug"></code> (cambiarlo ayuda a mitigar escaneos de bots).
                    </p>
                </div>
            </div>

            <div class="space-y-1">
                <label class="block text-xs font-bold text-gray-400 uppercase" for="install_app_url">Dirección pública del sitio</label>
                <input id="install_app_url" type="url" name="app_url" x-model="appUrl" required
                       placeholder="https://tudominio.com"
                       class="w-full bg-[#181818] border border-gray-700 focus:border-[#00FF99] focus:ring-1 focus:ring-[#00FF99] rounded-lg px-4 py-2 text-white text-sm">
                <p class="text-[11px] text-gray-500">La hemos detectado automáticamente. Revísala antes de finalizar, especialmente si el dominio usa HTTPS o una subcarpeta.</p>
            </div>
        </div>

        </section>
        <section data-install-step="admin" x-show="step === 1" class="space-y-4" aria-labelledby="install-admin-heading">
            <h3 id="install-admin-heading" tabindex="-1" class="text-lg font-bold text-white">Tu cuenta de administrador</h3>
            <p class="text-sm text-gray-400">Estas credenciales dan acceso al panel. La cuenta se creará al finalizar todos los pasos.</p>
        <!-- Super-Admin User Info -->
        <div class="p-5 bg-[#121212] rounded-xl border border-gray-800 space-y-4">
            <div class="text-xs font-mono font-bold text-[#00FF99] uppercase tracking-wider flex items-center gap-1.5">
                <span><i aria-hidden="true" class="fa-solid fa-user"></i></span>
                <span>Datos de la Cuenta Super-Administrador</span>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="space-y-1">
                    <label class="block text-xs font-mono font-bold text-gray-400 uppercase" for="install-admin-name">Nombre Completo</label>
                    <input type="text" id="install-admin-name" autocomplete="name" name="name" value="{{ old('name') }}" required
                           class="w-full bg-[#181818] border border-gray-700 focus:border-[#00FF99] focus:ring-1 focus:ring-[#00FF99] rounded-lg px-4 py-2 text-white font-mono text-sm">
                </div>

                <div class="space-y-1">
                    <label class="block text-xs font-mono font-bold text-gray-400 uppercase" for="install-admin-email">Correo Electrónico</label>
                    <input type="email" id="install-admin-email" autocomplete="email" name="email" value="{{ old('email') }}" required
                           class="w-full bg-[#181818] border border-gray-700 focus:border-[#00FF99] focus:ring-1 focus:ring-[#00FF99] rounded-lg px-4 py-2 text-white font-mono text-sm">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="space-y-1">
                    <label class="block text-xs font-mono font-bold text-gray-400 uppercase" for="install-admin-password">Contraseña</label>
                    <input type="password" id="install-admin-password" autocomplete="new-password" name="password" required minlength="8"
                           class="w-full bg-[#181818] border border-gray-700 focus:border-[#00FF99] focus:ring-1 focus:ring-[#00FF99] rounded-lg px-4 py-2 text-white font-mono text-sm"
                           placeholder="Mínimo 8 caracteres">
                </div>

                <div class="space-y-1">
                    <label class="block text-xs font-mono font-bold text-gray-400 uppercase" for="install-admin-confirmation">Confirmar Contraseña</label>
                    <input type="password" id="install-admin-confirmation" autocomplete="new-password" name="password_confirmation" required minlength="8"
                           class="w-full bg-[#181818] border border-gray-700 focus:border-[#00FF99] focus:ring-1 focus:ring-[#00FF99] rounded-lg px-4 py-2 text-white font-mono text-sm"
                           placeholder="Repite la contraseña">
                </div>
            </div>
        </div>

        </section>
        @include('install.company')
        @include('install.privacy')

        <div class="flex flex-wrap items-center justify-between gap-3 pt-4 border-t border-gray-800">
            <a href="{{ route('install.step3') }}" x-show="step === 0" class="text-xs text-gray-400 hover:text-white">&larr; Volver a las tablas</a>
            <button id="install-previous" type="button" x-cloak x-show="step > 0" @click="go(step - 1)" class="text-sm text-gray-300">&larr; Anterior</button>
            <button id="install-next" type="button" x-cloak x-show="step < steps.length - 1" @click="next()" class="btn-neon">Continuar &rarr;</button>
            <button id="install-finish" type="submit" x-show="step === steps.length - 1" :disabled="submitting" class="btn-neon">Finalizar instalación &rarr;</button>
        </div>
    </form>
</div>
@endsection

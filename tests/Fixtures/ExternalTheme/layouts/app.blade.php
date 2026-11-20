<!doctype html>
<html class="front-fixture-theme" lang="{{ $currentLocale ?? app()->getLocale() }}" @if(request()->attributes->get('cms.editorial_preview')) data-cms-preview @endif>
<head><meta charset="utf-8"><title>{{ ($seoMeta ?? null)?->title ?? trim(strip_tags($__env->yieldContent('title'))) ?: config('cms.name') }}</title><meta name="csrf-token" content="{{ csrf_token() }}">
@include('themes::components.seo-head')
@vite(cms_asset_entries(true))
@include('themes::components.analytics')
</head><body>
<!--cms-private:theme-fixture-theme::partials.header:start-->
@include('theme-fixture-theme::partials.header')
<!--cms-private:end-->
<main>@yield('content')</main>
@include('theme-fixture-theme::partials.footer')
<!--cms-private:themes::components.visitor-geo-modal:start-->
@include('themes::components.visitor-geo-modal')
<!--cms-private:end-->
<!--cms-private:themes::components.cookie-consent-modal:start-->
@include('themes::components.cookie-consent-modal')
<!--cms-private:end-->
@include('components.frontoffice-admin-bar')
@include('themes::components.scroll-magic')
@stack('admin_scripts') @stack('scripts')
@include('components.cms.overlays')
</body></html>

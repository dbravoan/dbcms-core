<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head><meta charset="utf-8"><title>{{ $notification->title }}</title></head>
<body>
    <h1>{{ $notification->title }}</h1>
    <p>{{ $notification->message }}</p>
    <p><a href="{{ $inboxUrl }}">{{ __('mail.module_notification.open_cms') }}</a></p>
</body>
</html>

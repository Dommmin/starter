{{-- Auto-reply to the sender: site brand, the owner's text and contact details only. --}}
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <title>{{ $siteName }}</title>
</head>
<body>
    @if ($logoUrl)
        <p><img src="{{ $logoUrl }}" alt="{{ $siteName }}" height="48"></p>
    @else
        <h1>{{ $siteName }}</h1>
    @endif
    <p>{!! nl2br(e($body)) !!}</p>
    <hr>
    <p>
        {{ $siteName }}
        @if ($contactEmail)<br>{{ $contactEmail }}@endif
        @if ($contactPhone)<br>{{ $contactPhone }}@endif
    </p>
    <p><small>{{ __('public.contactAutoReply.automatic') }}</small></p>
</body>
</html>

<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('admin.contact.mail.heading') }}</title>
</head>
<body>
    <h1>{{ __('admin.contact.mail.heading') }}</h1>
    <p><strong>{{ __('admin.contact.fields.name') }}:</strong> {{ $senderName }}</p>
    <p><strong>{{ __('admin.contact.fields.email') }}:</strong> {{ $senderEmail }}</p>
    <p><strong>{{ __('admin.contact.fields.locale') }}:</strong> {{ $locale }}</p>
    @if ($submittedAt)
        <p><strong>{{ __('admin.contact.fields.createdAt') }}:</strong> {{ $submittedAt }}</p>
    @endif
    <hr>
    <p>{!! nl2br(e($body)) !!}</p>
    <hr>
    <p>{{ __('admin.contact.mail.replyHint') }}</p>
</body>
</html>

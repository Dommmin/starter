{{-- Plain-text part: values are not HTML-escaped because text/plain is never rendered as markup. --}}
{{ __('admin.contact.mail.heading') }}

{{ __('admin.contact.fields.name') }}: {!! $senderName !!}
{{ __('admin.contact.fields.email') }}: {!! $senderEmail !!}
{{ __('admin.contact.fields.locale') }}: {{ $locale }}
@if ($submittedAt)
{{ __('admin.contact.fields.createdAt') }}: {{ $submittedAt }}
@endif

{!! $body !!}

--
{{ __('admin.contact.mail.replyHint') }}

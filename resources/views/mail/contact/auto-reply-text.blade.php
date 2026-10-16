{{-- Plain-text auto-reply to the sender; no submitted content. --}}
{{ $siteName }}

{!! $body !!}

--
{{ $siteName }}
@if ($contactEmail){{ $contactEmail }}
@endif
@if ($contactPhone){{ $contactPhone }}
@endif

{{ __('public.contactAutoReply.automatic') }}

{{-- Plain-text operator alert; the context holds only PII-free technical fields. --}}
Alert: {!! $event !!}
Environment: {!! $environment !!}
Release: {!! $release ?? 'unknown' !!}
Time (UTC): {{ $occurredAt }}

@foreach ($context as $key => $value)
{!! $key !!}: {!! is_bool($value) ? ($value ? 'true' : 'false') : ($value ?? 'null') !!}
@endforeach

Runbook: docs/foundation/10-local-environment-deployment-and-logs.md (Alerty i logi).

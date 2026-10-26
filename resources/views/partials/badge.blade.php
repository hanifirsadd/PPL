@php($status = is_string($status) ? \App\Enums\StatusAntrean::from($status) : $status)
<span class="badge badge--{{ strtolower($status->value) }}">{{ $status->label() }}</span>

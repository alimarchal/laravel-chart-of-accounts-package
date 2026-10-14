@props(['status', 'label' => null])
{{-- One status word as a coloured pill, so every list reads the same way. --}}
@php
    $key = strtolower((string) $status);
    $tone = match (true) {
        in_array($key, ['posted', 'paid', 'approved', 'active', 'matched', 'reconciled', 'closed', 'completed', 'settled', 'open', 'yes', 'enabled', 'set', 'sent', 'delivered', 'read', 'imported', 'created', 'create', 'new', 'accepted'], true) => 'ok',
        in_array($key, ['draft', 'pending', 'submitted', 'awaiting', 'preview', 'partial', 'skipped', 'skip', 'queued', 'optional', 'not sent', 'update', 'duplicate', 'scheduled', 'unmatched'], true) => 'warn',
        in_array($key, ['void', 'rejected', 'cancelled', 'canceled', 'failed', 'overdue', 'inactive', 'disabled', 'missing', 'error', 'invalid'], true) => 'bad',
        default => 'info',
    };
@endphp
<span {{ $attributes->class(['acc-badge', 'acc-badge--'.$tone]) }} style="text-transform:capitalize">{{ $label ?? $status }}</span>

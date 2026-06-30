{{--
    <x-status-badge status="Normal" type="sensor" />
    Notion-style pill badge: rounded-full, soft background + darker text of same hue.
    Never renders harsh saturated colors — always muted/pastel surface fills.

    Props:
      - status (string, required) — the status value to display
      - type (string, default 'sensor') — context: sensor | hdep | mortality | slot | general
      - class (string, default '') — additional classes on the wrapper

    Status mappings:
      sensor:    Normal → OK, Watch → Watch, Alert → Alert
      hdep:      numeric — >70 OK, 40–70 Watch, <40 Alert
      mortality: Disease/Heat Stress/etc → mapped to appropriate tone
      slot:      empty/occupied/sensor/full → mapped colors
      general:   ok/watch/alert (lowercase) → direct mapping
--}}
@props(['status', 'type' => 'sensor', 'class' => ''])

@php
    // Resolve to semantic bucket: ok | watch | alert | neutral
    $bucket = 'neutral';
    $label = $status;

    $statusLower = strtolower((string) $status);

    match ($type) {
        'sensor' => $bucket = match ($statusLower) {
            'normal', 'ok', 'good' => 'ok',
            'watch', 'warning', 'caution' => 'watch',
            'alert', 'critical', 'danger', 'error' => 'alert',
            default => 'neutral',
        },
        'hdep' => $bucket = match (true) {
            is_numeric($status) && (float) $status > 70 => 'ok',
            is_numeric($status) && (float) $status >= 40 => 'watch',
            is_numeric($status) && (float) $status < 40 => 'alert',
            default => 'neutral',
        },
        'mortality' => $bucket = match ($statusLower) {
            'disease', 'heat stress', 'predator' => 'alert',
            'injury', 'unknown' => 'watch',
            'other' => 'neutral',
            default => 'neutral',
        },
        'slot' => $bucket = match ($statusLower) {
            'empty' => 'neutral',
            'occupied', 'manual' => 'ok',
            'sensor' => 'watch',
            'full' => 'alert',
            default => 'neutral',
        },
        'general' => $bucket = match ($statusLower) {
            'ok', 'active', 'healthy', 'yes', 'true', 'on', 'enabled' => 'ok',
            'watch', 'pending', 'partial', 'paused' => 'watch',
            'alert', 'inactive', 'dead', 'no', 'false', 'off', 'disabled', 'error' => 'alert',
            default => 'neutral',
        },
        default => 'neutral',
    };

    // Color pairs from DESIGN-SYSTEM.md §2.4
    $colors = match ($bucket) {
        'ok'      => ['bg' => '#e8f5ec', 'text' => '#1f6b3a', 'border' => '#cfe8d6'],
        'watch'   => ['bg' => '#fdf3e0', 'text' => '#8a5a00', 'border' => '#f3e3bf'],
        'alert'   => ['bg' => '#fbe4e6', 'text' => '#9b1c24', 'border' => '#f3cdd0'],
        default   => ['bg' => '#f0f0f0', 'text' => '#615d59', 'border' => '#e6e6e6'],
    };

    // Human-readable label overrides
    $label = match ([$type, $statusLower]) {
        ['sensor', 'normal'] => 'Normal',
        ['sensor', 'ok'] => 'Normal',
        ['sensor', 'good'] => 'Normal',
        ['sensor', 'watch'] => 'Watch',
        ['sensor', 'warning'] => 'Watch',
        ['sensor', 'caution'] => 'Watch',
        ['sensor', 'alert'] => 'Alert',
        ['sensor', 'critical'] => 'Alert',
        ['slot', 'empty'] => 'Empty',
        ['slot', 'occupied'] => 'Occupied',
        ['slot', 'manual'] => 'Manual',
        ['slot', 'sensor'] => 'Sensor',
        ['slot', 'full'] => 'Full',
        default => ucfirst($statusLower),
    };
@endphp

<span
    class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold leading-none {{ $class }}"
    style="background-color: {{ $colors['bg'] }}; color: {{ $colors['text'] }}; border: 1px solid {{ $colors['border'] }};"
>
    {{ $label }}
</span>

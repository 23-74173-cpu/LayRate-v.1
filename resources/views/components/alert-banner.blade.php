{{--
    <x-alert-banner :alerts="$alerts" />
    Static full-width banner in the page flow (not floating).
    Soft red-tinted background, thin accent bar, muted icon, compact text.
    Consistent with Notion's soft-surface + darker-text pattern.

    Props:
      - alerts (Collection of Alert models, required) — only unread alerts should be passed
      - class (string, default '') — additional classes on the wrapper
      - dismissible (bool, default true) — show dismiss button
      - markReadRoute (string, default 'alerts.read-all') — route for mark-all-read POST
--}}
@props(['alerts', 'class' => '', 'dismissible' => true, 'markReadRoute' => 'alerts.read-all'])

@if($alerts->isNotEmpty())
<div
    id="alert-banner"
    class="w-full rounded-lg px-4 py-3 {{ $class }}"
    style="background-color: #fdf2f2; border: 1px solid #f3cdd0; border-left: 3px solid #e03e3e;"
    role="alert"
    aria-live="polite"
>
    <div class="flex items-start gap-3">
        {{-- Icon: outline, muted red, sized to text --}}
        <i data-lucide="alert-triangle" class="w-4 h-4 mt-0.5 shrink-0" style="color: #c44d4d;"></i>

        {{-- Content --}}
        <div class="flex-1 min-w-0">
            <p class="text-xs font-semibold tracking-[0.05em] uppercase" style="color: #c44d4d;">
                {{ $alerts->count() }} {{ Str::plural('alert', $alerts->count()) }}
            </p>
            <ul class="mt-0.5 space-y-0.5">
                @foreach($alerts->take(3) as $alert)
                <li class="text-sm" style="color: #31302e;">
                    <strong style="color: {{ $alert->cage?->color ?? '#615d59' }}">
                        {{ $alert->cage?->cage_code ?? '—' }}
                    </strong>
                    — {{ $alert->message }}
                </li>
                @endforeach
            </ul>
            @if($alerts->count() > 3)
            <p class="text-xs mt-0.5" style="color: #615d59;">
                +{{ $alerts->count() - 3 }} more
            </p>
            @endif
        </div>

        {{-- Actions --}}
        <div class="flex flex-col items-end gap-1 shrink-0">
            @if($dismissible)
            <button
                type="button"
                onclick="document.getElementById('alert-banner').remove()"
                class="p-1.5 rounded hover:bg-black/5 transition-colors"
                aria-label="Dismiss alerts"
            >
                <i data-lucide="x" class="w-3.5 h-3.5" style="color: #a39e98;"></i>
            </button>
            @endif
            <form action="{{ route($markReadRoute) }}" method="POST" class="inline">
                @csrf
                <button
                    type="submit"
                    class="text-xs font-medium transition-colors hover:underline"
                    style="color: #0075de;"
                >
                    Mark read
                </button>
            </form>
        </div>
    </div>
</div>
@endif

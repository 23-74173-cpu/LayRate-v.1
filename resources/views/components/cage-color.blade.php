{{--
    <x-cage-color :cage="$cage" />
    Renders a small colored dot + cage code label using the cage's identity color.
    Replaces all match($cage->cage_code) blocks across views.
    For inline style usage, use $cage->color or $cage->colorSoft directly.

    Props:
      - cage (Cage model, required)
      - dot (bool, default true) — show the colored dot
      - label (bool, default true) — show the cage code text
      - soft (bool, default false) — use soft bg tint instead of dot
      - class (string, default '') — additional classes on the wrapper
--}}
@props(['cage', 'dot' => true, 'label' => true, 'soft' => false, 'class' => ''])

<span class="inline-flex items-center gap-1.5 {{ $class }}">
    @if($soft)
        <span
            class="inline-block px-2 py-0.5 rounded-full text-xs font-semibold"
            style="background-color: {{ $cage->colorSoft }}; color: {{ $cage->color }};"
        >
            {{ $cage->cage_code }}
        </span>
    @else
        @if($dot)
            <span
                class="inline-block w-2.5 h-2.5 rounded-full shrink-0"
                style="background-color: {{ $cage->color }};"
            ></span>
        @endif
        @if($label)
            <span class="text-sm font-medium" style="color: {{ $cage->color }}">
                {{ $cage->cage_code }}
            </span>
        @endif
    @endif
</span>

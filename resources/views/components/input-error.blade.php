{{--
    <x-input-error name="email" />
    Renders validation error text below an input field.
    Consistent across all forms: small text in alert color.
    When used alongside an input, also apply the error border class:
      class="{{ $errors->has('email') ? 'border-[#9b1c24] ring-1 ring-[#f3cdd0]' : '' }}"

    Props:
      - name (string, required) — the field name to check for errors
      - text (string, optional) — override the error message
      - class (string, default '') — additional classes on the wrapper
--}}
@props(['name', 'text' => null, 'class' => ''])

@error($name)
<div class="flex items-center gap-1 mt-1 {{ $class }}">
    <svg class="w-3 h-3 shrink-0" style="color: #9b1c24;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
    </svg>
    <p class="text-xs" style="color: #9b1c24;">{{ $text ?? $message }}</p>
</div>
@enderror

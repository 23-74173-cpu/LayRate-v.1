{{--
    <x-saved-note-picker category="Mortality" target="textarea[name='notes']" />

    "Use a saved note" dropdown shown above a section's notes box. Lists the
    notes saved under that category on the Notes page (notes typed in the
    section are saved there automatically, see Note::saveFromSection()), and
    picking one fills the notes box so it doesn't have to be retyped.

    Props:
      - category (string, required) — one of Note::CATEGORIES
      - target (string, required)   — CSS selector of the notes field, looked up
                                      inside the same <form> first
      - notes (Collection, optional) — pre-loaded suggestions; loaded here when omitted

    The <select> has no name, so it is never submitted with the form.
--}}
@props(['category', 'target', 'notes' => null])

@php
    $savedNotes = $notes ?? \App\Models\Note::suggestionsFor($category);
    $placeholder = 'Use a saved note…';
@endphp

<select data-note-picker data-note-category="{{ $category }}" data-note-target="{{ $target }}"
        data-placeholder="{{ $placeholder }}"
        aria-label="Use a saved {{ strtolower($category) }} note"
        onchange="window.LayRateNotes && window.LayRateNotes.apply(this)"
        @disabled($savedNotes->isEmpty())
        {{ $attributes->merge(['class' => 'w-full border border-[#D9D9D9] rounded-lg px-3 py-2 text-xs bg-white text-[#6B7280] mb-1.5 focus:outline-none focus:ring-2 focus:ring-[#102A4C]/30 focus:border-[#102A4C] disabled:opacity-60']) }}>
    <option value="">{{ $savedNotes->isEmpty() ? 'No saved ' . strtolower($category) . ' notes yet' : $placeholder }}</option>
    @foreach($savedNotes as $savedNote)
    <option value="{{ $savedNote->body }}">{{ \Illuminate\Support\Str::limit(preg_replace('/\s+/', ' ', $savedNote->body), 70) }}</option>
    @endforeach
</select>

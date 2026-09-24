@extends('layouts.app')
@section('title', 'Notes')

@section('content')
@php
    // Badge colors per category (same soft palette as the status badges).
    $categoryColors = [
        'General'     => ['#f1f1ef', '#615d59'],
        'Production'  => ['#fdf3e0', '#8a5a00'],
        'Mortality'   => ['#fbe4e6', '#9b1c24'],
        'Hens'        => ['#e8f5ec', '#1f6b3a'],
        'Feed'        => ['#eef6e3', '#3f6212'],
        'Environment' => ['#e3f1f5', '#1e5f74'],
        'Reports'     => ['#e8eefb', '#1D4E8F'],
    ];
    $editFailed = (bool) session('reopen_edit_note');
    $addOld = fn ($key, $default = null) => $editFailed ? $default : old($key, $default);
    $totalNotes = $categoryCounts->sum();
@endphp
<div class="space-y-5">

    <x-page-header title="Notes" subtitle="Notes and reminders, sorted by section. Notes typed in Mortality, Egg Logging, Feed, and Hens are saved here too." />

    {{-- ── Add Note ── --}}
    <div class="rounded-xl border p-6" style="background-color: #ffffff; border-color: #e6e6e6;">
        <form method="POST" action="{{ route('notes.store') }}">
            @csrf
            <input type="hidden" name="return_category" value="{{ $category }}">
            <div class="flex flex-col sm:flex-row gap-3">
                <div class="flex-1">
                    <textarea name="body" rows="2" required maxlength="{{ \App\Models\Note::MAX_LENGTH }}"
                              placeholder="Write a note…"
                              class="w-full border rounded-lg px-3 py-2.5 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#0075de] focus:ring-offset-1 resize-y"
                              style="border-color: #e6e6e6; color: #1f1f1f;">{{ $addOld('body') }}</textarea>
                    @unless($editFailed)<x-input-error name="body" />@endunless
                </div>
                <div class="flex flex-col gap-3 sm:w-48">
                    <select name="category" aria-label="Category"
                            class="w-full border rounded-lg px-3 py-2.5 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#0075de] focus:ring-offset-1"
                            style="border-color: #e6e6e6; color: #1f1f1f;">
                        @foreach($categories as $cat)
                        <option value="{{ $cat }}" @selected($addOld('category', $category ?? \App\Models\Note::DEFAULT_CATEGORY) === $cat)>{{ $cat }}</option>
                        @endforeach
                    </select>
                    @unless($editFailed)<x-input-error name="category" />@endunless
                    <select name="cage_id" aria-label="Cage tag"
                            class="w-full border rounded-lg px-3 py-2.5 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#0075de] focus:ring-offset-1"
                            style="border-color: #e6e6e6; color: #1f1f1f;">
                        <option value="">No cage tag</option>
                        @foreach($cages as $cage)
                        <option value="{{ $cage->id }}" @selected((string) $addOld('cage_id') === (string) $cage->id)>{{ $cage->cage_code }}</option>
                        @endforeach
                    </select>
                    <x-button type="submit" class="px-6 py-2.5 whitespace-nowrap">
                        Add Note
                    </x-button>
                </div>
            </div>
        </form>
    </div>

    {{-- ── Category Filter ── --}}
    <nav class="flex flex-wrap gap-2" aria-label="Filter notes by category">
        <a href="{{ route('notes.index') }}"
           class="px-3 py-1.5 rounded-full text-xs font-medium transition-colors border"
           style="{{ $category === null ? 'background-color:#102A4C;color:#ffffff;border-color:#102A4C;' : 'background-color:#ffffff;color:#615d59;border-color:#e6e6e6;' }}"
           @if($category === null) aria-current="page" @endif>
            All <span class="opacity-75">({{ $totalNotes }})</span>
        </a>
        @foreach($categories as $cat)
        <a href="{{ route('notes.index', ['category' => $cat]) }}"
           class="px-3 py-1.5 rounded-full text-xs font-medium transition-colors border"
           style="{{ $category === $cat ? 'background-color:#102A4C;color:#ffffff;border-color:#102A4C;' : 'background-color:#ffffff;color:#615d59;border-color:#e6e6e6;' }}"
           @if($category === $cat) aria-current="page" @endif>
            {{ $cat }} <span class="opacity-75">({{ $categoryCounts[$cat] ?? 0 }})</span>
        </a>
        @endforeach
    </nav>

    {{-- ── Notes List ── --}}
    <div class="rounded-xl border overflow-hidden" style="background-color: #ffffff; border-color: #e6e6e6;">
        @forelse($notes as $note)
        @php [$catBg, $catText] = $categoryColors[$note->category] ?? $categoryColors['General']; @endphp
        <div class="flex items-start gap-3 px-5 py-4 {{ !$loop->last ? 'border-b' : '' }}" style="border-color: #e6e6e6;">
            <i data-lucide="sticky-note" class="w-4 h-4 mt-0.5 shrink-0" style="color: #a39e98;"></i>
            <div class="flex-1 min-w-0">
                <p class="text-sm whitespace-pre-wrap break-words" style="color: #1f1f1f;">{{ $note->body }}</p>
                <div class="flex flex-wrap items-center gap-2 mt-1.5 text-xs" style="color: #a39e98;">
                    <span class="px-2 py-0.5 rounded-full text-xs font-medium" style="background-color: {{ $catBg }}; color: {{ $catText }};">
                        {{ $note->category }}
                    </span>
                    <span>{{ $note->created_at->format('m/d/Y g:i A') }}</span>
                    @if($note->updated_at->ne($note->created_at))
                    <span>· edited</span>
                    @endif
                    @if($note->cage)
                    <span class="px-2 py-0.5 rounded-full text-xs font-medium"
                          style="background-color: {{ $note->cage->colorSoft }}; color: {{ $note->cage->color }};">
                        {{ $note->cage->cage_code }}
                    </span>
                    @endif
                </div>
            </div>
            <div class="flex items-center gap-1 shrink-0">
                <button onclick="openNoteEdit({{ $note->id }}, {{ Js::from($note->body) }}, {{ $note->cage_id ?? 'null' }}, {{ Js::from($note->category) }})"
                        class="p-1.5 rounded hover:bg-black/5 transition-colors" style="color: #615d59;" aria-label="Edit note">
                    <i data-lucide="pencil" class="w-3.5 h-3.5"></i>
                </button>
                <form method="POST" action="{{ route('notes.destroy', $note) }}"
                      data-confirm="Delete this note?" data-confirm-action="Delete" data-confirm-severity="destructive">
                    @csrf
                    @method('DELETE')
                    <input type="hidden" name="return_category" value="{{ $category }}">
                    <button type="submit" class="p-1.5 rounded hover:bg-red-50 transition-colors" style="color: #a39e98;" aria-label="Delete note">
                        <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                    </button>
                </form>
            </div>
        </div>
        @empty
        <div class="p-10 text-center text-sm" style="color: #a39e98;">
            {{ $category ? 'No ' . strtolower($category) . ' notes yet.' : 'No notes yet. Write your first note above.' }}
        </div>
        @endforelse
    </div>

    <x-paginator :paginator="$notes" />

    {{-- ── Edit Note Modal ── --}}
    <div id="noteEditModal" data-modal  class="hidden fixed inset-0 z-50 flex items-center justify-center p-4" role="dialog" aria-modal="true" style="display: none;">
        <div class="absolute inset-0" style="background-color: rgba(0,0,0,0.35); backdrop-filter: blur(4px);" onclick="closeNoteEdit()"></div>
        <div class="relative w-full max-w-md rounded-2xl p-6 max-h-screen max-h-[100dvh] overflow-y-auto" style="background-color: #ffffff; box-shadow: rgba(0,0,0,0.01) 0 0.175px 1.041px, rgba(0,0,0,0.02) 0 0 0.8px 2.925px, rgba(0,0,0,0.027) 0 2.025px 7.847px, rgba(0,0,0,0.04) 0 4px 18px, rgba(0,0,0,0.05) 0 23px 52px;">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-[20px] font-semibold leading-[1.4] tracking-[-0.125px]" style="color: #1f1f1f;">Edit Note</h2>
                <button onclick="closeNoteEdit()" class="p-1.5 rounded-full hover:bg-black/5 transition-colors" aria-label="Close">
                    <i data-lucide="x" class="w-5 h-5" style="color: #615d59;"></i>
                </button>
            </div>
            <form id="noteEditForm" method="POST" action="">
                @csrf
                @method('PUT')
                <input type="hidden" name="return_category" value="{{ $category }}">
                <textarea name="body" id="noteEditBody" rows="4" required maxlength="{{ \App\Models\Note::MAX_LENGTH }}"
                          class="w-full border rounded-lg px-3 py-2.5 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#0075de] focus:ring-offset-1 resize-y mb-3"
                          style="border-color: #e6e6e6; color: #1f1f1f;">{{ old('body') }}</textarea>
                @if($editFailed)<x-input-error name="body" />@endif
                <label for="noteEditCategory" class="block text-xs tracking-wider text-[#6B7280] mb-1.5">CATEGORY</label>
                <select name="category" id="noteEditCategory"
                        class="w-full border rounded-lg px-3 py-2.5 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#0075de] focus:ring-offset-1 mb-3"
                        style="border-color: #e6e6e6; color: #1f1f1f;">
                    @foreach($categories as $cat)
                    <option value="{{ $cat }}">{{ $cat }}</option>
                    @endforeach
                </select>
                @if($editFailed)<x-input-error name="category" />@endif
                <label for="noteEditCage" class="block text-xs tracking-wider text-[#6B7280] mb-1.5">CAGE TAG</label>
                <select name="cage_id" id="noteEditCage"
                        class="w-full border rounded-lg px-3 py-2.5 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#0075de] focus:ring-offset-1 mb-4"
                        style="border-color: #e6e6e6; color: #1f1f1f;">
                    <option value="">No cage tag</option>
                    @foreach($cages as $cage)
                    <option value="{{ $cage->id }}">{{ $cage->cage_code }}</option>
                    @endforeach
                </select>
                <div class="flex items-center justify-end gap-3">
                    <button type="button" onclick="closeNoteEdit()"
                            class="px-4 py-2 text-sm font-medium rounded-lg transition-colors"
                            style="color: #1f1f1f; border: 1px solid #e6e6e6;"
                            onmouseover="this.style.backgroundColor='#f6f5f4'"
                            onmouseout="this.style.backgroundColor='transparent'">
                        Cancel
                    </button>
                    <x-button type="submit" class="px-5 py-2">
                        Save
                    </x-button>
                </div>
            </form>
        </div>
    </div>

@if($editFailed)
@php $editNote = \App\Models\Note::find(session('reopen_edit_note')); @endphp
@if($editNote)
<x-modal-reopen modal-id="noteEditModal" session-key="reopen_edit_note" guard="editNote">
    openNoteEdit(
        {{ $editNote->id }},
        {{ Js::from(old('body', $editNote->body)) }},
        {{ Js::from(old('cage_id', $editNote->cage_id)) }},
        {{ Js::from(old('category', $editNote->category)) }}
    );
</x-modal-reopen>
@endif
@endif

</div>

<script>
function openNoteEdit(id, body, cageId, category) {
    document.getElementById('noteEditForm').action = '/notes/' + id;
    document.getElementById('noteEditBody').value = body;
    document.getElementById('noteEditCage').value = cageId === null || cageId === undefined ? '' : String(cageId);
    document.getElementById('noteEditCategory').value = category || 'General';
    document.getElementById('noteEditModal').style.display = 'flex';
    document.getElementById('noteEditBody').focus();
}
function closeNoteEdit() {
    document.getElementById('noteEditModal').style.display = 'none';
}
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeNoteEdit();
});
</script>
@endsection

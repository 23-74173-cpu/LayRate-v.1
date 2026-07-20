@extends('layouts.app')
@section('title', 'Notes')

@section('content')
<div class="space-y-5">

    <x-page-header title="Notes" subtitle="Short notes and reminders — optionally tagged to a cage" />

    {{-- ── Add Note ── --}}
    <div class="rounded-xl border p-6" style="background-color: #ffffff; border-color: #e6e6e6;">
        <form method="POST" action="{{ route('notes.store') }}"
              data-confirm="Add this note?" data-confirm-action="Add Note">
            @csrf
            <div class="flex flex-col sm:flex-row gap-3">
                <div class="flex-1">
                    <textarea name="body" rows="2" required maxlength="2000"
                              placeholder="Write a note…"
                              class="w-full border rounded-lg px-3 py-2.5 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#0075de] focus:ring-offset-1 resize-y"
                              style="border-color: #e6e6e6; color: #1f1f1f;">{{ old('body') }}</textarea>
                    <x-input-error name="body" />
                </div>
                <div class="flex sm:flex-col gap-3 sm:w-48">
                    <select name="cage_id"
                            class="w-full border rounded-lg px-3 py-2.5 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#0075de] focus:ring-offset-1"
                            style="border-color: #e6e6e6; color: #1f1f1f;">
                        <option value="">No cage tag</option>
                        @foreach($cages as $cage)
                        <option value="{{ $cage->id }}" @selected(old('cage_id') == $cage->id)>{{ $cage->cage_code }}</option>
                        @endforeach
                    </select>
                    <x-button type="submit" class="px-6 py-2.5 whitespace-nowrap">
                        Add Note
                    </x-button>
                </div>
            </div>
        </form>
    </div>

    {{-- ── Notes List ── --}}
    <div class="rounded-xl border overflow-hidden" style="background-color: #ffffff; border-color: #e6e6e6;">
        @forelse($notes as $note)
        <div class="flex items-start gap-3 px-5 py-4 {{ !$loop->last ? 'border-b' : '' }}" style="border-color: #e6e6e6;">
            <i data-lucide="sticky-note" class="w-4 h-4 mt-0.5 shrink-0" style="color: #a39e98;"></i>
            <div class="flex-1 min-w-0">
                <p class="text-sm whitespace-pre-wrap break-words" style="color: #1f1f1f;">{{ $note->body }}</p>
                <div class="flex items-center gap-2 mt-1.5 text-xs" style="color: #a39e98;">
                    <span>{{ $note->created_at->format('M j, Y g:i A') }}</span>
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
                <button onclick="openNoteEdit({{ $note->id }}, {{ Js::from($note->body) }}, {{ $note->cage_id ?? 'null' }})"
                        class="p-1.5 rounded hover:bg-black/5 transition-colors" style="color: #615d59;" aria-label="Edit note">
                    <i data-lucide="pencil" class="w-3.5 h-3.5"></i>
                </button>
                <form method="POST" action="{{ route('notes.destroy', $note) }}"
                      data-confirm="Delete this note?" data-confirm-action="Delete">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="p-1.5 rounded hover:bg-red-50 transition-colors" style="color: #a39e98;" aria-label="Delete note">
                        <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                    </button>
                </form>
            </div>
        </div>
        @empty
        <div class="p-10 text-center text-sm" style="color: #a39e98;">
            No notes yet. Write your first note above.
        </div>
        @endforelse
    </div>

    <x-paginator :paginator="$notes" />

    {{-- ── Edit Note Modal ── --}}
    <div id="noteEditModal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4" role="dialog" aria-modal="true">
        <div class="absolute inset-0" style="background-color: rgba(0,0,0,0.35); backdrop-filter: blur(4px);" onclick="closeNoteEdit()"></div>
        <div class="relative w-full max-w-md rounded-2xl p-6" style="background-color: #ffffff; box-shadow: rgba(0,0,0,0.01) 0 0.175px 1.041px, rgba(0,0,0,0.02) 0 0 0.8px 2.925px, rgba(0,0,0,0.027) 0 2.025px 7.847px, rgba(0,0,0,0.04) 0 4px 18px, rgba(0,0,0,0.05) 0 23px 52px;">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-[20px] font-semibold leading-[1.4] tracking-[-0.125px]" style="color: #1f1f1f;">Edit Note</h2>
                <button onclick="closeNoteEdit()" class="p-1.5 rounded-full hover:bg-black/5 transition-colors" aria-label="Close">
                    <i data-lucide="x" class="w-5 h-5" style="color: #615d59;"></i>
                </button>
            </div>
            <form id="noteEditForm" method="POST" action="">
                @csrf
                @method('PUT')
                <textarea name="body" id="noteEditBody" rows="4" required maxlength="2000"
                          class="w-full border rounded-lg px-3 py-2.5 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#0075de] focus:ring-offset-1 resize-y mb-3"
                          style="border-color: #e6e6e6; color: #1f1f1f;"></textarea>
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

</div>

<script>
function openNoteEdit(id, body, cageId) {
    document.getElementById('noteEditForm').action = '/notes/' + id;
    document.getElementById('noteEditBody').value = body;
    document.getElementById('noteEditCage').value = cageId === null ? '' : cageId;
    document.getElementById('noteEditModal').classList.remove('hidden');
    document.getElementById('noteEditBody').focus();
}
function closeNoteEdit() {
    document.getElementById('noteEditModal').classList.add('hidden');
}
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeNoteEdit();
});
</script>
@endsection

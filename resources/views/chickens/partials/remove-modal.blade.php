<div id="removeModal" class="hidden fixed inset-0 z-50 min-h-screen min-h-[100dvh] items-center justify-center p-4" role="dialog" aria-modal="true">
    {{-- Backdrop --}}
    <div class="absolute inset-0 h-full min-h-screen min-h-[100dvh]" style="background-color: rgba(0,0,0,0.35); backdrop-filter: blur(4px);" onclick="closeRemoveModal()"></div>

    {{-- Card --}}
    <div class="relative w-full max-w-md rounded-2xl p-6 overflow-hidden" style="background-color: #ffffff; box-shadow: rgba(0,0,0,0.01) 0 0.175px 1.041px, rgba(0,0,0,0.02) 0 0 0.8px 2.925px, rgba(0,0,0,0.027) 0 2.025px 7.847px, rgba(0,0,0,0.04) 0 4px 18px, rgba(0,0,0,0.05) 0 23px 52px;">
        <form id="removeForm" method="POST" action="{{ route('chickens.remove') }}">
            @csrf

            {{-- Header --}}
            <div class="flex items-center justify-between mb-5">
                <h2 class="text-[20px] font-semibold leading-[1.4] tracking-[-0.125px]" style="color: #1f1f1f;">Remove Chickens</h2>
                <button type="button" onclick="closeRemoveModal()" class="p-1.5 rounded-full hover:bg-black/5 transition-colors" aria-label="Close">
                    <i data-lucide="x" class="w-5 h-5" style="color: #615d59;"></i>
                </button>
            </div>

            {{-- Body --}}
            <div class="space-y-4">
                <p class="text-sm text-[#6B7280]">
                    Removing <strong id="removeCount" class="text-red-600">0</strong> hen(s)
                </p>

                {{-- Source info --}}
                <div id="removeSourceInfo" class="hidden p-3 bg-[#F5F6F8] rounded border border-[#E5E7EB] text-xs space-y-1">
                    <div class="flex gap-2">
                        <span class="text-[#9CA3AF] w-16">Source:</span>
                        <span id="removeSourceText" class="text-[#333] font-medium"></span>
                    </div>
                    <div class="flex gap-2">
                        <span class="text-[#9CA3AF] w-16">Breed:</span>
                        <span id="removeSourceBreed" class="text-[#333]"></span>
                    </div>
                </div>

                {{-- Record as mortality --}}
                <div class="flex items-center gap-2">
                    <input type="checkbox" name="record_mortality" id="recordMortality" value="1" checked
                           class="w-4 h-4 text-[#002D5E] rounded border-[#D9D9D9] focus:ring-[#002D5E]"
                           onchange="document.getElementById('mortalityFields').classList.toggle('hidden', !this.checked)">
                    <label for="recordMortality" class="text-sm text-[#333]">Record as mortality</label>
                </div>

                {{-- Mortality fields --}}
                <div id="mortalityFields" class="space-y-3">
                    <div>
                        <label class="block text-xs font-medium text-[#6B7280] mb-1">Reason</label>
                        <select name="reason" id="removeReason"
                                class="w-full border border-[#D9D9D9] rounded px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-[#002D5E]">
                            <option value="">Select reason...</option>
                            @foreach(['Disease', 'Heat Stress', 'Injury', 'Predator', 'Unknown', 'Other'] as $reason)
                            <option value="{{ $reason }}">{{ $reason }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-[#6B7280] mb-1">Notes (optional)</label>
                        <textarea name="notes" id="removeNotes" rows="2" placeholder="Additional details..."
                                  class="w-full border border-[#D9D9D9] rounded px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-[#002D5E] resize-none"></textarea>
                    </div>
                </div>

                <div id="removeError" class="hidden text-xs text-red-500"></div>
            </div>

            {{-- Footer --}}
            <div class="flex gap-3 mt-5">
                <button type="button" onclick="closeRemoveModal()"
                        class="flex-1 py-2.5 text-sm font-medium rounded-lg transition-colors"
                        style="color: #1f1f1f; border: 1px solid #e6e6e6;"
                        onmouseover="this.style.backgroundColor='#f6f5f4'"
                        onmouseout="this.style.backgroundColor='transparent'">
                    Cancel
                </button>
                <button type="submit" id="removeSubmitBtn"
                        class="flex-1 py-2.5 text-sm font-medium rounded-full text-white transition-opacity"
                        style="background-color: #9b1c24;"
                        onmouseover="this.style.backgroundColor='#7a161d'"
                        onmouseout="this.style.backgroundColor='#9b1c24'">
                    Remove Chickens
                </button>
            </div>

            <input type="hidden" name="hen_ids" id="removeHenIds" value="">
        </form>
    </div>
</div>

@push('scripts')
<script>
function openRemoveModal(henIds, count, sourceInfo, breed) {
    document.getElementById('removeCount').textContent = count;
    document.getElementById('removeHenIds').value = henIds;

    if (sourceInfo) {
        document.getElementById('removeSourceInfo').classList.remove('hidden');
        document.getElementById('removeSourceText').textContent = sourceInfo;
        document.getElementById('removeSourceBreed').textContent = breed || '';
    } else {
        document.getElementById('removeSourceInfo').classList.add('hidden');
    }

    document.getElementById('recordMortality').checked = true;
    document.getElementById('mortalityFields').classList.remove('hidden');
    document.getElementById('removeReason').value = '';
    document.getElementById('removeNotes').value = '';
    document.getElementById('removeError').classList.add('hidden');

    document.getElementById('removeModal').classList.remove('hidden');
    document.getElementById('removeModal').classList.add('flex');
}
function closeRemoveModal() {
    document.getElementById('removeModal').classList.add('hidden');
    document.getElementById('removeModal').classList.remove('flex');
}

window.openRemoveModal = openRemoveModal;
window.closeRemoveModal = closeRemoveModal;

// Escape key closes modal
(function() {
    if (window.__removeModalEscapeBound) return;
    window.__removeModalEscapeBound = true;
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeRemoveModal();
    });
})();
</script>
@endpush

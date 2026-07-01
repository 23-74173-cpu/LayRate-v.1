<div id="removeModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/40" hidden>
    <div class="bg-white rounded-lg shadow-xl w-full max-w-md mx-4 overflow-hidden">
        <form id="removeForm" method="POST" action="{{ route('chickens.remove') }}">
            @csrf

            {{-- Header --}}
            <div class="flex items-center justify-between px-5 py-3 border-b border-[#D9D9D9] bg-[#F5F6F8]">
                <h3 class="text-sm font-semibold text-[#333]">Remove Chickens</h3>
                <button type="button" onclick="closeRemoveModal()" class="text-[#9CA3AF] hover:text-[#333]" aria-label="Close">
                    <i data-lucide="x" class="w-4 h-4"></i>
                </button>
            </div>

            {{-- Body --}}
            <div class="p-5 space-y-4">
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
            <div class="px-5 py-3 border-t border-[#D9D9D9] flex items-center justify-end gap-3 bg-[#F5F6F8]">
                <button type="button" onclick="closeRemoveModal()"
                        class="px-4 py-2 text-sm border border-[#D9D9D9] rounded hover:bg-[#E5E7EB]">
                    Cancel
                </button>
                <button type="submit" id="removeSubmitBtn"
                        class="px-5 py-2 text-sm bg-red-600 text-white rounded hover:bg-red-700">
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
    document.getElementById('removeModal').removeAttribute('hidden');
}
function closeRemoveModal() {
    document.getElementById('removeModal').classList.add('hidden');
    document.getElementById('removeModal').setAttribute('hidden', '');
}

window.openRemoveModal = openRemoveModal;
window.closeRemoveModal = closeRemoveModal;
</script>
@endpush

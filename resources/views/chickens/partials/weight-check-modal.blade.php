<div id="weightCheckModal" class="hidden fixed inset-0 z-50 min-h-screen min-h-[100dvh] items-center justify-center p-4" role="dialog" aria-modal="true">
    <div class="absolute inset-0 h-full min-h-screen min-h-[100dvh]" style="background-color: rgba(0,0,0,0.35); backdrop-filter: blur(4px);" onclick="closeWeightCheckModal()"></div>

    <div class="relative w-full max-w-sm rounded-2xl p-6 overflow-hidden" style="background-color: #ffffff; box-shadow: rgba(0,0,0,0.01) 0 0.175px 1.041px, rgba(0,0,0,0.02) 0 0 0.8px 2.925px, rgba(0,0,0,0.027) 0 2.025px 7.847px, rgba(0,0,0,0.04) 0 4px 18px, rgba(0,0,0,0.05) 0 23px 52px;">
        <form method="POST" action="{{ route('chickens.weight-check') }}">
            @csrf

            <div class="flex items-center justify-between mb-5">
                <h2 class="text-[20px] font-semibold leading-[1.4] tracking-[-0.125px]" style="color: #1f1f1f;">Record Weight</h2>
                <button type="button" onclick="closeWeightCheckModal()" class="p-1.5 rounded-full hover:bg-black/5 transition-colors" aria-label="Close">
                    <i data-lucide="x" class="w-5 h-5" style="color: #615d59;"></i>
                </button>
            </div>

            <div class="space-y-4">
                <div id="weightHenInfo" class="p-3 bg-[#F5F6F8] rounded border border-[#E5E7EB] text-xs">
                    <span class="text-[#9CA3AF]">Hen:</span>
                    <span id="weightHenText" class="text-[#333] font-medium ml-1"></span>
                </div>

                <div>
                    <label class="block text-xs font-medium text-[#6B7280] mb-1">Date <span class="text-red-500">*</span></label>
                    <input type="date" name="check_date" required value="{{ today()->toDateString() }}"
                           class="w-full border border-[#D9D9D9] rounded px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-[#002D5E]">
                    <x-input-error name="check_date" />
                </div>

                <div>
                    <label class="block text-xs font-medium text-[#6B7280] mb-1">Weight (kg) <span class="text-red-500">*</span></label>
                    <input type="number" name="weight_kg" required step="0.01" min="0" max="20" placeholder="e.g. 1.75"
                           class="w-full border border-[#D9D9D9] rounded px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-[#002D5E]">
                    <x-input-error name="weight_kg" />
                </div>

                <div>
                    <label class="block text-xs font-medium text-[#6B7280] mb-1">Notes</label>
                    <input type="text" name="notes" placeholder="Optional"
                           class="w-full border border-[#D9D9D9] rounded px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-[#002D5E]">
                    <x-input-error name="notes" />
                </div>

                <input type="hidden" name="hen_id" id="weightHenId" value="">
            </div>

            <div class="flex gap-3 mt-5">
                <button type="button" onclick="closeWeightCheckModal()"
                        class="flex-1 py-2.5 text-sm font-medium rounded-lg transition-colors"
                        style="color: #1f1f1f; border: 1px solid #e6e6e6;"
                        onmouseover="this.style.backgroundColor='#f6f5f4'"
                        onmouseout="this.style.backgroundColor='transparent'">
                    Cancel
                </button>
                <button type="submit"
                        class="flex-1 py-2.5 text-sm font-medium rounded-full text-white transition-opacity"
                        style="background-color: #0075de;"
                        onmouseover="if(!this.disabled) this.style.opacity='0.85'"
                        onmouseout="this.style.opacity='1'">
                    Record
                </button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
function openWeightCheckModal(henId, henLabel) {
    document.getElementById('weightHenId').value = henId;
    document.getElementById('weightHenText').textContent = henLabel;
    document.getElementById('weightCheckModal').classList.remove('hidden');
    document.getElementById('weightCheckModal').classList.add('flex');
}

function closeWeightCheckModal() {
    document.getElementById('weightCheckModal').classList.add('hidden');
    document.getElementById('weightCheckModal').classList.remove('flex');
}

window.openWeightCheckModal = openWeightCheckModal;
window.closeWeightCheckModal = closeWeightCheckModal;

(function() {
    if (window.__weightCheckEscapeBound) return;
    window.__weightCheckEscapeBound = true;
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeWeightCheckModal();
    });
})();
</script>
@endpush

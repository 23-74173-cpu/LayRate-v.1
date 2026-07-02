<div id="removalModal" class="hidden fixed inset-0 z-50 min-h-screen min-h-[100dvh] items-center justify-center p-4" role="dialog" aria-modal="true">
    <div class="absolute inset-0 h-full min-h-screen min-h-[100dvh]" style="background-color: rgba(0,0,0,0.35); backdrop-filter: blur(4px);" onclick="closeRemovalModal()"></div>
    <div class="relative w-full max-w-md rounded-2xl p-6 overflow-hidden" style="background-color: #ffffff; box-shadow: rgba(0,0,0,0.01) 0 0.175px 1.041px, rgba(0,0,0,0.02) 0 0 0.8px 2.925px, rgba(0,0,0,0.027) 0 2.025px 7.847px, rgba(0,0,0,0.04) 0 4px 18px, rgba(0,0,0,0.05) 0 23px 52px;">
        <form method="POST" action="{{ route('chickens.removal') }}">
            @csrf
            <div class="flex items-center justify-between mb-5">
                <h2 class="text-[20px] font-semibold leading-[1.4] tracking-[-0.125px]" style="color: #1f1f1f;">Remove / Sell Chicken</h2>
                <button type="button" onclick="closeRemovalModal()" class="p-1.5 rounded-full hover:bg-black/5 transition-colors" aria-label="Close">
                    <i data-lucide="x" class="w-5 h-5" style="color: #615d59;"></i>
                </button>
            </div>
            <div class="space-y-4">
                <div id="removalHenInfo" class="p-3 bg-[#F5F6F8] rounded border border-[#E5E7EB] text-xs">
                    <span class="text-[#9CA3AF]">Hen:</span>
                    <span id="removalHenText" class="text-[#333] font-medium ml-1"></span>
                </div>
                <div>
                    <label class="block text-xs font-medium text-[#6B7280] mb-1">Date <span class="text-red-500">*</span></label>
                    <input type="date" name="removal_date" required value="{{ today()->toDateString() }}"
                           class="w-full border border-[#D9D9D9] rounded px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-[#002D5E]">
                    <x-input-error name="removal_date" />
                </div>
                <div>
                    <label class="block text-xs font-medium text-[#6B7280] mb-1">Reason <span class="text-red-500">*</span></label>
                    <input type="text" name="reason" required placeholder="e.g. Sold, Transferred to another farm"
                           class="w-full border border-[#D9D9D9] rounded px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-[#002D5E]">
                    <x-input-error name="reason" />
                </div>
                <div>
                    <label class="block text-xs font-medium text-[#6B7280] mb-1">Destination</label>
                    <input type="text" name="destination" placeholder="e.g. Buyer name, Farm name"
                           class="w-full border border-[#D9D9D9] rounded px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-[#002D5E]">
                    <x-input-error name="destination" />
                </div>
                <div>
                    <label class="block text-xs font-medium text-[#6B7280] mb-1">Notes</label>
                    <textarea name="notes" rows="2" placeholder="Optional..."
                              class="w-full border border-[#D9D9D9] rounded px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-[#002D5E] resize-none"></textarea>
                    <x-input-error name="notes" />
                </div>
                <input type="hidden" name="hen_id" id="removalHenId" value="">
            </div>
            <div class="flex gap-3 mt-5">
                <button type="button" onclick="closeRemovalModal()"
                        class="flex-1 py-2.5 text-sm font-medium rounded-lg transition-colors"
                        style="color: #1f1f1f; border: 1px solid #e6e6e6;"
                        onmouseover="this.style.backgroundColor='#f6f5f4'"
                        onmouseout="this.style.backgroundColor='transparent'">Cancel</button>
                <button type="submit"
                        class="flex-1 py-2.5 text-sm font-medium rounded-full text-white transition-opacity"
                        style="background-color: #0075de;"
                        onmouseover="if(!this.disabled) this.style.opacity='0.85'"
                        onmouseout="this.style.opacity='1'">Confirm Removal</button>
            </div>
        </form>
    </div>
</div>
@push('scripts')
<script>
function openRemovalModal(henId, henLabel) {
    document.getElementById('removalHenId').value = henId;
    document.getElementById('removalHenText').textContent = henLabel;
    document.getElementById('removalModal').classList.remove('hidden');
    document.getElementById('removalModal').classList.add('flex');
}
function closeRemovalModal() {
    document.getElementById('removalModal').classList.add('hidden');
    document.getElementById('removalModal').classList.remove('flex');
}
window.openRemovalModal = openRemovalModal;
window.closeRemovalModal = closeRemovalModal;
(function() { if (window.__removalModalEscapeBound) return; window.__removalModalEscapeBound = true;
document.addEventListener('keydown', function(e) { if (e.key === 'Escape') closeRemovalModal(); }); })();
</script>
@endpush

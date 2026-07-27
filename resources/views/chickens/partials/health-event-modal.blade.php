<div id="healthEventModal" class="hidden fixed inset-0 z-50 min-h-screen min-h-[100dvh] items-center justify-center p-4" role="dialog" aria-modal="true">
    <div class="absolute inset-0 h-full min-h-screen min-h-[100dvh]" style="background-color: rgba(0,0,0,0.35); backdrop-filter: blur(4px);" onclick="closeHealthEventModal()"></div>

    <div class="relative w-full max-w-md rounded-2xl p-6 max-h-screen max-h-[100dvh] overflow-y-auto" style="background-color: #ffffff; box-shadow: rgba(0,0,0,0.01) 0 0.175px 1.041px, rgba(0,0,0,0.02) 0 0 0.8px 2.925px, rgba(0,0,0,0.027) 0 2.025px 7.847px, rgba(0,0,0,0.04) 0 4px 18px, rgba(0,0,0,0.05) 0 23px 52px;">
        <form method="POST" action="{{ route('chickens.health-event') }}">
            @csrf

            <div class="flex items-center justify-between mb-5">
                <h2 class="text-[20px] font-semibold leading-[1.4] tracking-[-0.125px]" style="color: #1f1f1f;">Log Health Event</h2>
                <button type="button" onclick="closeHealthEventModal()" class="p-1.5 rounded-full hover:bg-black/5 transition-colors" aria-label="Close">
                    <i data-lucide="x" class="w-5 h-5" style="color: #615d59;"></i>
                </button>
            </div>

            <div class="space-y-4">
                <div id="healthHenInfo" class="p-3 bg-[#F5F6F8] rounded border border-[#E5E7EB] text-xs space-y-1">
                    <div class="flex gap-2">
                        <span class="text-[#9CA3AF] w-16">Hen:</span>
                        <span id="healthHenText" class="text-[#333] font-medium"></span>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-medium text-[#6B7280] mb-1">Date <span class="text-red-500">*</span></label>
                    <input type="date" name="event_date" required value="{{ old('event_date', today()->toDateString()) }}"
                           class="w-full border border-[#D9D9D9] rounded px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-[#002D5E]">
                    <x-input-error name="event_date" />
                </div>

                <div>
                    <label class="block text-xs font-medium text-[#6B7280] mb-1">Event Type <span class="text-red-500">*</span></label>
                    <select name="event_type" required
                            class="w-full border border-[#D9D9D9] rounded px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-[#002D5E]">
                        <option value="sick" {{ old('event_type') === 'sick' ? 'selected' : '' }}>Sick</option>
                        <option value="treated" {{ old('event_type') === 'treated' ? 'selected' : '' }}>Treated</option>
                        <option value="recovered" {{ old('event_type') === 'recovered' ? 'selected' : '' }}>Recovered</option>
                    </select>
                    <x-input-error name="event_type" />
                </div>

                <div>
                    <label class="block text-xs font-medium text-[#6B7280] mb-1">Description</label>
                    <input type="text" name="description" value="{{ old('description') }}" placeholder="e.g. Respiratory symptoms, Antibiotics administered"
                           class="w-full border border-[#D9D9D9] rounded px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-[#002D5E]">
                    <x-input-error name="description" />
                </div>

                <div>
                    <label class="block text-xs font-medium text-[#6B7280] mb-1">Notes</label>
                    <textarea name="notes" rows="2" placeholder="Optional details..."
                              class="w-full border border-[#D9D9D9] rounded px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-[#002D5E] resize-none">{{ old('notes') }}</textarea>
                    <x-input-error name="notes" />
                </div>

                <input type="hidden" name="hen_id" id="healthHenId" value="">
            </div>

            <div class="flex gap-3 mt-5">
                <button type="button" onclick="closeHealthEventModal()"
                        class="flex-1 py-2.5 text-sm font-medium rounded-lg border border-[#e6e6e6] text-[#1f1f1f] hover:bg-[#f6f5f4] transition-colors">
                    Cancel
                </button>
                <x-button type="submit" class="flex-1 py-2.5">
                    Log Event
                </x-button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
function openHealthEventModal(henId, henLabel) {
    document.getElementById('healthHenId').value = henId;
    document.getElementById('healthHenText').textContent = henLabel;
    document.getElementById('healthEventModal').classList.remove('hidden');
    document.getElementById('healthEventModal').classList.add('flex');
}

function closeHealthEventModal() {
    var el = document.getElementById('healthEventModal');
    if (el) { el.classList.add('hidden'); el.classList.remove('flex'); }
}

window.openHealthEventModal = openHealthEventModal;
window.closeHealthEventModal = closeHealthEventModal;

(function() {
    if (window.__healthEventModalEscapeBound) return;
    window.__healthEventModalEscapeBound = true;
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeHealthEventModal();
    });
})();
</script>
@endpush

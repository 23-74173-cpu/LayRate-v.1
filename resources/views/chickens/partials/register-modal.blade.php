<div id="registerModal" class="hidden fixed inset-0 z-50 min-h-screen min-h-[100dvh] items-center justify-center p-4" role="dialog" aria-modal="true">
    <div class="absolute inset-0 h-full min-h-screen min-h-[100dvh]" style="background-color: rgba(0,0,0,0.35); backdrop-filter: blur(4px);" onclick="closeRegisterModal()"></div>

    <div class="relative w-full max-w-lg rounded-2xl p-6 overflow-hidden" style="background-color: #ffffff; box-shadow: rgba(0,0,0,0.01) 0 0.175px 1.041px, rgba(0,0,0,0.02) 0 0 0.8px 2.925px, rgba(0,0,0,0.027) 0 2.025px 7.847px, rgba(0,0,0,0.04) 0 4px 18px, rgba(0,0,0,0.05) 0 23px 52px;">
        <form method="POST" action="{{ route('chickens.store') }}">
            @csrf

            <div class="flex items-center justify-between mb-5">
                <h2 class="text-[20px] font-semibold leading-[1.4] tracking-[-0.125px]" style="color: #1f1f1f;">Register New Chickens</h2>
                <button type="button" onclick="closeRegisterModal()" class="p-1.5 rounded-full hover:bg-black/5 transition-colors" aria-label="Close">
                    <i data-lucide="x" class="w-5 h-5" style="color: #615d59;"></i>
                </button>
            </div>

            <div class="space-y-4 max-h-[65vh] overflow-y-auto pr-1">
                <div class="p-3 bg-blue-50 border border-blue-200 rounded text-xs text-blue-700">
                    <i data-lucide="info" class="w-3.5 h-3.5 inline"></i>
                    Creates unplaced hens. Use <strong>Bulk Add</strong> to assign them to cages later.
                </div>

                {{-- Quantity --}}
                <div>
                    <label class="block text-xs font-medium text-[#6B7280] mb-1">Quantity <span class="text-red-500">*</span></label>
                    <input type="number" name="quantity" required min="1" max="100" value="{{ old('quantity', 1) }}"
                           class="w-full border border-[#D9D9D9] rounded px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-[#002D5E]">
                    <x-input-error name="quantity" />
                </div>

                {{-- Breed --}}
                <div>
                    <label class="block text-xs font-medium text-[#6B7280] mb-1">Breed <span class="text-red-500">*</span></label>
                    <select name="breed" required
                            class="w-full border border-[#D9D9D9] rounded px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-[#002D5E]">
                        <option value="">Select breed...</option>
                        @foreach(['ISA Brown', 'Lohmann Brown-Classic', 'Dekalb White', 'Hy-Line Brown', 'Novogen Brown'] as $b)
                        <option value="{{ $b }}" {{ old('breed') === $b ? 'selected' : '' }}>{{ $b }}</option>
                        @endforeach
                    </select>
                    <x-input-error name="breed" />
                </div>

                {{-- Source/Origin --}}
                <div>
                    <label class="block text-xs font-medium text-[#6B7280] mb-1">Source / Origin</label>
                    <input type="text" name="source" value="{{ old('source') }}" placeholder="e.g. breeder name or supplier"
                           class="w-full border border-[#D9D9D9] rounded px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-[#002D5E]">
                    <x-input-error name="source" />
                </div>

                <div class="grid grid-cols-2 gap-4">
                    {{-- Date Acquired --}}
                    <div>
                        <label class="block text-xs font-medium text-[#6B7280] mb-1">Date Acquired <span class="text-red-500">*</span></label>
                        <input type="date" name="date_acquired" required value="{{ old('date_acquired', today()->toDateString()) }}"
                               class="w-full border border-[#D9D9D9] rounded px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-[#002D5E]">
                        <x-input-error name="date_acquired" />
                    </div>

                    {{-- Age at Acquisition --}}
                    <div>
                        <label class="block text-xs font-medium text-[#6B7280] mb-1">Age at Acquisition (weeks) <span class="text-red-500">*</span></label>
                        <input type="number" name="age_at_placement_weeks" required min="0" value="{{ old('age_at_placement_weeks', '0') }}"
                               class="w-full border border-[#D9D9D9] rounded px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-[#002D5E]">
                        <x-input-error name="age_at_placement_weeks" />
                    </div>
                </div>

                {{-- Initial Health Status --}}
                <div>
                    <label class="block text-xs font-medium text-[#6B7280] mb-1">Initial Health Status</label>
                    <input type="text" name="initial_health_status" value="{{ old('initial_health_status') }}" placeholder="e.g. Healthy, Requires observation"
                           class="w-full border border-[#D9D9D9] rounded px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-[#002D5E]">
                    <x-input-error name="initial_health_status" />
                </div>

                {{-- Notes --}}
                <div>
                    <label class="block text-xs font-medium text-[#6B7280] mb-1">Notes</label>
                    <textarea name="notes" rows="2" placeholder="Optional notes..." maxlength="1000"
                              class="w-full border border-[#D9D9D9] rounded px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-[#002D5E] resize-none">{{ old('notes') }}</textarea>
                    <x-input-error name="notes" />
                </div>
            </div>

            <div class="flex gap-3 mt-5">
                <button type="button" onclick="closeRegisterModal()"
                        class="flex-1 py-2.5 text-sm font-medium rounded-lg transition-colors"
                        style="color: #1f1f1f; border: 1px solid #e6e6e6;"
                        onmouseover="this.style.backgroundColor='#f6f5f4'"
                        onmouseout="this.style.backgroundColor='transparent'">
                    Cancel
                </button>
                <button type="submit"
                        class="flex-1 py-2.5 text-sm font-medium rounded-full text-white transition-opacity"
                        style="background-color: #0075de;"
                        onmouseover="this.style.opacity='0.85'"
                        onmouseout="this.style.opacity='1'">
                    Register Chickens
                </button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
function openRegisterModal() {
    document.getElementById('registerModal').classList.remove('hidden');
    document.getElementById('registerModal').classList.add('flex');
}

function closeRegisterModal() {
    document.getElementById('registerModal').classList.add('hidden');
    document.getElementById('registerModal').classList.remove('flex');
}

window.openRegisterModal = openRegisterModal;
window.closeRegisterModal = closeRegisterModal;

(function() {
    if (window.__registerModalEscapeBound) return;
    window.__registerModalEscapeBound = true;
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeRegisterModal();
    });
})();
</script>
@endpush

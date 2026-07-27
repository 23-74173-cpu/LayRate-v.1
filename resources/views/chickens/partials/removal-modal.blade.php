<div id="removalModal" class="hidden fixed inset-0 z-50 min-h-screen min-h-[100dvh] items-center justify-center p-4" role="dialog" aria-modal="true">
    <div class="absolute inset-0 h-full min-h-screen min-h-[100dvh]" style="background-color: rgba(0,0,0,0.35); backdrop-filter: blur(4px);" onclick="closeRemovalModal()"></div>
    <div class="relative w-full max-w-md rounded-2xl p-6 max-h-screen max-h-[100dvh] overflow-y-auto" style="background-color: #ffffff; box-shadow: rgba(0,0,0,0.01) 0 0.175px 1.041px, rgba(0,0,0,0.02) 0 0 0.8px 2.925px, rgba(0,0,0,0.027) 0 2.025px 7.847px, rgba(0,0,0,0.04) 0 4px 18px, rgba(0,0,0,0.05) 0 23px 52px;">
        <form method="POST" action="{{ route('chickens.removal') }}">
            @csrf
            <div class="flex items-center justify-between mb-5">
                <h2 class="text-[20px] font-semibold leading-[1.4] tracking-[-0.125px]" style="color: #1f1f1f;">Remove / Sell <span id="removalModalTitle">Chicken</span></h2>
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
                    <input type="date" name="removal_date" required value="{{ old('removal_date', today()->toDateString()) }}"
                           class="w-full border border-[#D9D9D9] rounded px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-[#002D5E]">
                    <x-input-error name="removal_date" />
                </div>
                <div>
                    <label class="block text-xs font-medium text-[#6B7280] mb-1">Reason <span class="text-red-500">*</span></label>
                    <input type="text" name="reason" required value="{{ old('reason') }}" placeholder="e.g. Sold, Transferred to another farm"
                           class="w-full border border-[#D9D9D9] rounded px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-[#002D5E]">
                    <x-input-error name="reason" />
                </div>
                <div>
                    <label class="block text-xs font-medium text-[#6B7280] mb-1">Destination</label>
                    <input type="text" name="destination" value="{{ old('destination') }}" placeholder="e.g. Buyer name, Farm name"
                           class="w-full border border-[#D9D9D9] rounded px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-[#002D5E]">
                    <x-input-error name="destination" />
                </div>
                <div>
                    <label class="block text-xs font-medium text-[#6B7280] mb-1">Notes</label>
                    <textarea name="notes" rows="2" placeholder="Optional..."
                              class="w-full border border-[#D9D9D9] rounded px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-[#002D5E] resize-none">{{ old('notes') }}</textarea>
                    <x-input-error name="notes" />
                </div>
                <input type="hidden" name="hen_id" id="removalHenId" value="">
            </div>
            <div class="flex gap-3 mt-5">
                <button type="button" onclick="closeRemovalModal()"
                        class="flex-1 py-2.5 text-sm font-medium rounded-lg border border-[#e6e6e6] text-[#1f1f1f] hover:bg-[#f6f5f4] transition-colors">Cancel</button>
                <x-button type="button" onclick="submitRemoval(this.form)" class="flex-1 py-2.5">
                    Confirm Removal
                </x-button>
            </div>
        </form>
    </div>
</div>
@push('scripts')
<script>
function openRemovalModal(henId, henLabel) {
    document.getElementById('removalHenId').value = henId;
    document.getElementById('removalHenText').textContent = henLabel;
    document.getElementById('removalModalTitle').textContent = henId.indexOf(',') > -1 ? 'Hens' : 'Chicken';
    document.getElementById('removalModal').classList.remove('hidden');
    document.getElementById('removalModal').classList.add('flex');
}
function closeRemovalModal() {
    var el = document.getElementById('removalModal');
    if (el) { el.classList.add('hidden'); el.classList.remove('flex'); }
}
window.openRemovalModal = openRemovalModal;
window.closeRemovalModal = closeRemovalModal;
(function() { if (window.__removalModalEscapeBound) return; window.__removalModalEscapeBound = true;
document.addEventListener('keydown', function(e) { if (e.key === 'Escape') closeRemovalModal(); }); })();

function submitRemoval(form) {
    var henInput = form.querySelector('input[name="hen_id"]');
    var henCount = henInput && henInput.value ? henInput.value.split(',').length : 1;
    confirmModal(
        'Remove / sell ' + henCount + ' hen(s) from the flock? This permanently deactivates them and cannot be undone.',
        { submit: function() { ajaxRemoval(form); } },
        'Remove', 'destructive'
    );
}

function ajaxRemoval(form) {
    form.querySelectorAll('.removal-error').forEach(function(el) { el.remove(); });
    form.querySelectorAll('.has-removal-error').forEach(function(el) {
        el.classList.remove('has-removal-error');
    });

    var formData = new FormData(form);
    var data = {};
    formData.forEach(function(v, k) { data[k] = v; });

    fetch(form.action, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'Accept': 'application/json'
        },
        body: JSON.stringify(data)
    }).then(function(r) {
        return r.json().then(function(j) { return { ok: r.ok, json: j }; });
    }).then(function(result) {
        if (result.ok) {
            closeRemovalModal();
            if (typeof showNotification === 'function') {
                showNotification(result.json.message, 'success');
            }
            var frame = document.getElementById('chickens-removal-records');
            if (frame) frame.src = frame.src;
        } else {
            var errors = result.json.errors || {};
            Object.keys(errors).forEach(function(field) {
                var input = form.querySelector('[name="' + field + '"]');
                if (!input) return;
                var wrapper = input.closest('div');
                if (!wrapper) return;
                wrapper.classList.add('has-removal-error');
                input.style.borderColor = '#9b1c24';
                input.classList.add('ring-1');
                input.style.setProperty('--tw-ring-color', '#f3cdd0');
                var msg = errors[field][0];
                var errorEl = document.createElement('p');
                errorEl.className = 'removal-error flex items-center gap-1.5 text-sm mt-1';
                errorEl.style.color = '#9b1c24';
                errorEl.innerHTML = '<i data-lucide="alert-circle" class="w-4 h-4" style="color: #9b1c24; min-width: 16px;"></i> ' + msg;
                wrapper.appendChild(errorEl);
            });
            if (window.lucide) lucide.createIcons();
        }
    }).catch(function() {
        form.submit();
    });
}
</script>
@endpush
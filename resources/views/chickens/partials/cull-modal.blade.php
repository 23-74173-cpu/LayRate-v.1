<div id="cullModal" class="hidden fixed inset-0 z-50 min-h-screen min-h-[100dvh] items-center justify-center p-4" role="dialog" aria-modal="true">
    <div class="absolute inset-0 h-full min-h-screen min-h-[100dvh]" style="background-color: rgba(0,0,0,0.35); backdrop-filter: blur(4px);" onclick="closeCullModal()"></div>
    <div class="relative w-full max-w-md rounded-2xl p-6 overflow-hidden" style="background-color: #ffffff; box-shadow: rgba(0,0,0,0.01) 0 0.175px 1.041px, rgba(0,0,0,0.02) 0 0 0.8px 2.925px, rgba(0,0,0,0.027) 0 2.025px 7.847px, rgba(0,0,0,0.04) 0 4px 18px, rgba(0,0,0,0.05) 0 23px 52px;">
        <form method="POST" action="{{ route('chickens.cull') }}">
            @csrf
            <div class="flex items-center justify-between mb-5">
                <h2 class="text-[20px] font-semibold leading-[1.4] tracking-[-0.125px]" style="color: #1f1f1f;">Cull <span id="cullModalTitle">Chicken</span></h2>
                <button type="button" onclick="closeCullModal()" class="p-1.5 rounded-full hover:bg-black/5 transition-colors" aria-label="Close">
                    <i data-lucide="x" class="w-5 h-5" style="color: #615d59;"></i>
                </button>
            </div>
            <div class="space-y-4">
                <div id="cullHenInfo" class="p-3 bg-[#F5F6F8] rounded border border-[#E5E7EB] text-xs">
                    <span class="text-[#9CA3AF]">Hen:</span>
                    <span id="cullHenText" class="text-[#333] font-medium ml-1"></span>
                </div>
                <div>
                    <label class="block text-xs font-medium text-[#6B7280] mb-1">Cull Date <span class="text-red-500">*</span></label>
                    <input type="date" name="cull_date" required value="{{ old('cull_date', today()->toDateString()) }}"
                           class="w-full border border-[#D9D9D9] rounded px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-[#002D5E]">
                    <x-input-error name="cull_date" />
                </div>
                <div>
                    <label class="block text-xs font-medium text-[#6B7280] mb-1">Reason <span class="text-red-500">*</span></label>
                    <select name="reason" required
                            class="w-full border border-[#D9D9D9] rounded px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-[#002D5E]">
                        <option value="">Select reason...</option>
                        <option value="low_production" {{ old('reason') === 'low_production' ? 'selected' : '' }}>Low Production</option>
                        <option value="illness" {{ old('reason') === 'illness' ? 'selected' : '' }}>Illness</option>
                        <option value="aggression" {{ old('reason') === 'aggression' ? 'selected' : '' }}>Aggression</option>
                        <option value="age" {{ old('reason') === 'age' ? 'selected' : '' }}>Age</option>
                        <option value="other" {{ old('reason') === 'other' ? 'selected' : '' }}>Other</option>
                    </select>
                    <x-input-error name="reason" />
                </div>
                <div>
                    <label class="block text-xs font-medium text-[#6B7280] mb-1">Notes</label>
                    <textarea name="notes" rows="2" placeholder="Optional..."
                              class="w-full border border-[#D9D9D9] rounded px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-[#002D5E] resize-none">{{ old('notes') }}</textarea>
                    <x-input-error name="notes" />
                </div>
                <input type="hidden" name="hen_id" id="cullHenId" value="">
            </div>
            <div class="flex gap-3 mt-5">
                <button type="button" onclick="closeCullModal()"
                        class="flex-1 py-2.5 text-sm font-medium rounded-lg border border-[#e6e6e6] text-[#1f1f1f] hover:bg-[#f6f5f4] transition-colors">Cancel</button>
                <x-button type="button" onclick="submitCull(this.form)" class="flex-1 py-2.5">
                    Confirm Cull
                </x-button>
            </div>
        </form>
    </div>
</div>
@push('scripts')
<script>
function openCullModal(henId, henLabel) {
    document.getElementById('cullHenId').value = henId;
    document.getElementById('cullHenText').textContent = henLabel;
    document.getElementById('cullModalTitle').textContent = henId.indexOf(',') > -1 ? 'Hens' : 'Chicken';
    document.getElementById('cullModal').classList.remove('hidden');
    document.getElementById('cullModal').classList.add('flex');
}
function closeCullModal() {
    var el = document.getElementById('cullModal');
    if (el) { el.classList.add('hidden'); el.classList.remove('flex'); }
}
window.openCullModal = openCullModal;
window.closeCullModal = closeCullModal;
(function() { if (window.__cullModalEscapeBound) return; window.__cullModalEscapeBound = true;
document.addEventListener('keydown', function(e) { if (e.key === 'Escape') closeCullModal(); }); })();

function submitCull(form) {
    var henInput = form.querySelector('input[name="hen_id"]');
    var henCount = henInput && henInput.value ? henInput.value.split(',').length : 1;
    confirmModal(
        'Cull ' + henCount + ' hen(s)? This permanently deactivates them and cannot be undone.',
        { submit: function() { ajaxCull(form); } },
        'Cull', 'destructive'
    );
}

function ajaxCull(form) {
    form.querySelectorAll('.cull-error').forEach(function(el) { el.remove(); });
    form.querySelectorAll('.has-cull-error').forEach(function(el) {
        el.classList.remove('has-cull-error');
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
            closeCullModal();
            if (typeof showNotification === 'function') {
                showNotification(result.json.message, 'success');
            }
            // Reload both frames a cull affects: the culling-records tab
            // (where the new record now appears) and the inventory list
            // (where the culled hen's status flips to Inactive) — Move and
            // Remove already reload inventory for the same reason; this was
            // the one of the three left out. Also fixed: `frame.src = frame.src`
            // is a no-op in most browsers (no attribute change to react to),
            // so even the culling-records reload wasn't reliably firing —
            // clear-then-reset like Move/Remove do to force a real reload.
            var cullingFrame = document.getElementById('chickens-culling-records');
            if (cullingFrame) {
                var cullingSrc = cullingFrame.src;
                cullingFrame.src = '';
                cullingFrame.src = cullingSrc;
            }
            var inventoryFrame = document.getElementById('chickens-inventory-list');
            if (inventoryFrame) {
                var inventorySrc = inventoryFrame.src;
                inventoryFrame.src = '';
                inventoryFrame.src = inventorySrc;
            }
        } else {
            var errors = result.json.errors || {};
            Object.keys(errors).forEach(function(field) {
                var input = form.querySelector('[name="' + field + '"]');
                if (!input) return;
                var wrapper = input.closest('div');
                if (!wrapper) return;
                wrapper.classList.add('has-cull-error');
                input.style.borderColor = '#9b1c24';
                input.classList.add('ring-1');
                input.style.setProperty('--tw-ring-color', '#f3cdd0');
                var msg = errors[field][0];
                var errorEl = document.createElement('p');
                errorEl.className = 'cull-error flex items-center gap-1.5 text-sm mt-1';
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
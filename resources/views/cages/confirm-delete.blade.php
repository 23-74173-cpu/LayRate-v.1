@extends('layouts.app')
@section('title', 'Confirm Delete Cage')

@section('content')
<div class="max-w-lg mx-auto">
    @if ($errors->any())
    <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-5 text-sm" style="color: #9b1c24;">
        <ul class="list-disc list-inside space-y-1">
            @foreach ($errors->all() as $error)
            <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
    @endif

    <x-card>
        <x-slot:headerSlot>
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-full bg-red-100 flex items-center justify-center shrink-0">
                    <i data-lucide="alert-triangle" class="w-5 h-5 text-red-600"></i>
                </div>
                <div>
                    <h1 class="text-lg font-semibold" style="color: #1f1f1f;">Delete {{ $cage->cage_code }}?</h1>
                    <p class="text-sm" style="color: #6B7280;">This action cannot be undone.</p>
                </div>
            </div>
        </x-slot:headerSlot>

        <form method="POST" action="{{ route('cages.force-destroy', $cage) }}" id="deleteForm" data-cage-code="{{ $cage->cage_code }}"
              data-counts="{{ json_encode(['slots' => $slotCount, 'sensorSlots' => $sensorSlotCount, 'hens' => $henCount, 'production' => $productionLogCount, 'environment' => $envLogCount, 'feed' => $feedLogCount, 'mortality' => $mortalityCount, 'alerts' => $alertCount], JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_HEX_TAG) }}">
            @csrf @method('DELETE')

            {{-- Data-loss summary --}}
            <div class="mb-5">
                <p class="text-sm font-semibold mb-2" style="color: #1f1f1f;">Records that will be permanently deleted:</p>
                <div class="bg-[#F9F9F7] border border-[#D9D9D9] rounded-lg p-4 space-y-1.5 text-sm">
                    <div class="flex justify-between">
                        <span style="color: #6B7280;">Cage slots</span>
                        <span class="font-medium" style="color: #1f1f1f;">{{ $slotCount }}</span>
                    </div>
                    <div class="flex justify-between" data-sensor-slot-count="{{ $sensorSlotCount }}">
                        <span style="color: #6B7280;">Sensor-equipped slots</span>
                        <span class="font-medium" style="color: #1f1f1f;">{{ $sensorSlotCount }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span style="color: #6B7280;">Active hen records</span>
                        <span class="font-medium" style="color: {{ $henCount > 0 ? '#9b1c24' : '#1f1f1f' }};">{{ $henCount }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span style="color: #6B7280;">Production logs</span>
                        <span class="font-medium" style="color: {{ $productionLogCount > 0 ? '#9b1c24' : '#1f1f1f' }};">{{ $productionLogCount }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span style="color: #6B7280;">Environmental logs</span>
                        <span class="font-medium" style="color: {{ $envLogCount > 0 ? '#9b1c24' : '#1f1f1f' }};">{{ $envLogCount }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span style="color: #6B7280;">Feed consumption logs</span>
                        <span class="font-medium" style="color: {{ $feedLogCount > 0 ? '#9b1c24' : '#1f1f1f' }};">{{ $feedLogCount }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span style="color: #6B7280;">Mortality records</span>
                        <span class="font-medium" style="color: {{ $mortalityCount > 0 ? '#9b1c24' : '#1f1f1f' }};">{{ $mortalityCount }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span style="color: #6B7280;">Alerts</span>
                        <span class="font-medium" style="color: {{ $alertCount > 0 ? '#9b1c24' : '#1f1f1f' }};">{{ $alertCount }}</span>
                    </div>
                </div>
            </div>

            {{-- Preserve options --}}
            <div class="mb-5">
                <p class="text-sm font-semibold mb-2" style="color: #1f1f1f;">Preserve historical data</p>
                <p class="text-xs mb-3" style="color: #6B7280;">Check the types of records you want to keep (FKeys will be nulled, records themselves are preserved).</p>
                <div class="space-y-2">
                    <label class="flex items-center gap-2 text-sm cursor-pointer">
                        <input type="checkbox" name="preserve_production" value="1" {{ old('preserve_production', true) ? 'checked' : '' }}
                               class="rounded border-gray-300 text-navy focus:ring-navy">
                        <span style="color: #1f1f1f;">Preserve production logs ({{ $productionLogCount }})</span>
                    </label>
                    <label class="flex items-center gap-2 text-sm cursor-pointer">
                        <input type="checkbox" name="preserve_mortality" value="1" {{ old('preserve_mortality', true) ? 'checked' : '' }}
                               class="rounded border-gray-300 text-navy focus:ring-navy">
                        <span style="color: #1f1f1f;">Preserve mortality records ({{ $mortalityCount }})</span>
                    </label>
                    <label class="flex items-center gap-2 text-sm cursor-pointer">
                        <input type="checkbox" name="preserve_feed" value="1" {{ old('preserve_feed', true) ? 'checked' : '' }}
                               class="rounded border-gray-300 text-navy focus:ring-navy">
                        <span style="color: #1f1f1f;">Preserve feed consumption logs ({{ $feedLogCount }})</span>
                    </label>
                    <label class="flex items-center gap-2 text-sm cursor-pointer">
                        <input type="checkbox" name="preserve_environment" value="1" {{ old('preserve_environment', true) ? 'checked' : '' }}
                               class="rounded border-gray-300 text-navy focus:ring-navy">
                        <span style="color: #1f1f1f;">Preserve environmental logs ({{ $envLogCount }})</span>
                    </label>
                </div>
            </div>

            {{-- Hens action --}}
            <div class="mb-5">
                <p class="text-sm font-semibold mb-2" style="color: #1f1f1f;">Active hens ({{ $henCount }})</p>
                <div class="space-y-2">
                    <label class="flex items-center gap-2 text-sm cursor-pointer">
                        <input type="radio" name="hens_action" value="move" {{ old('hens_action', 'move') === 'move' ? 'checked' : '' }}
                               class="border-gray-300 text-navy focus:ring-navy">
                        <span style="color: #1f1f1f;">Detach hens (null out cage_slot_id, keep hen records)</span>
                    </label>
                    <label class="flex items-center gap-2 text-sm cursor-pointer">
                        <input type="radio" name="hens_action" value="delete" {{ old('hens_action') === 'delete' ? 'checked' : '' }}
                               class="border-gray-300 text-[#9b1c24] focus:ring-[#9b1c24]">
                        <span style="color: #9b1c24;">Delete all hen records permanently</span>
                    </label>
                </div>
            </div>

            {{-- Return sensors --}}
            <div class="mb-5">
                <label class="flex items-center gap-2 text-sm cursor-pointer">
                    <input type="checkbox" name="return_sensors" value="1" {{ old('return_sensors', true) ? 'checked' : '' }}
                           class="rounded border-gray-300 text-navy focus:ring-navy">
                    <span style="color: #1f1f1f;">Return sensors to spare inventory</span>
                </label>
            </div>

            {{-- Type to confirm --}}
            <div class="mb-5">
                <label for="confirmationInput" class="block text-sm font-semibold mb-1.5" style="color: #1f1f1f;">
                    Type <span class="font-mono font-bold text-red-600">{{ $cage->cage_code }}</span> to confirm
                </label>
                <input type="text" name="confirmation" id="confirmationInput" value="{{ old('confirmation') }}" autocomplete="off" required
                       oninput="document.getElementById('deleteBtn').disabled = (this.value !== '{{ $cage->cage_code }}')"
                       class="w-full border rounded-lg px-3 py-2.5 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-1"
                       style="border-color: #e6e6e6; color: #1f1f1f;"
                       placeholder="Type {{ $cage->cage_code }} here...">
                <x-input-error name="confirmation" />
            </div>

                <div class="flex gap-3" id="deleteOptionsActions">
                <a href="{{ route('cages.index') }}"
                   class="flex-1 border border-[#D9D9D9] py-2.5 rounded-lg text-sm text-center hover:bg-[#F5F6F8]"
                   style="color: #6B7280;">
                    Cancel
                </a>
                <button type="button" id="deleteBtn" disabled data-review-submit
                        class="flex-1 py-2.5 text-sm font-medium rounded-full text-white transition-opacity disabled:opacity-40 disabled:cursor-not-allowed"
                        style="background-color: #9b1c24;">
                    Delete {{ $cage->cage_code }}
                </button>
            </div>

            <section id="deleteReview" class="hidden mt-5 rounded-lg border border-red-200 bg-red-50 p-4" aria-labelledby="confirmDeleteReview" tabindex="-1" hidden>
                <h2 id="confirmDeleteReview" class="text-base font-semibold text-red-900" data-dialog-initial-focus tabindex="-1">Final review: delete {{ $cage->cage_code }}?</h2>
                <p class="mt-1 text-sm text-red-900">This is permanent. Review these effects before confirming.</p>
                <dl class="mt-3 space-y-1 text-sm text-[#1f1f1f]" id="deleteReviewSummary"></dl>
                <div class="mt-4 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                    <button type="button" data-review-back class="min-h-11 rounded-lg border border-[#D9D9D9] bg-white px-4 py-2 text-sm font-medium text-[#1f1f1f] hover:bg-[#F5F6F8]">Back and review choices</button>
                    <button type="submit" id="confirmDeleteSubmit" class="min-h-11 rounded-lg px-4 py-2 text-sm font-semibold text-white" style="background-color: #9b1c24;">Permanently delete {{ $cage->cage_code }}</button>
                </div>
            </section>
        </form>
    </x-card>
</div>
<script>
(() => {
    const form = document.getElementById('deleteForm');
    const firstActions = document.getElementById('deleteOptionsActions');
    const review = document.getElementById('deleteReview');
    const summary = document.getElementById('deleteReviewSummary');
    const reviewButton = form && form.querySelector('[data-review-submit]');
    const backButton = form && form.querySelector('[data-review-back]');
    if (!form || !firstActions || !review || !summary || !reviewButton || !backButton) return;

    const countLabels = {
        slots: 'Cage slots', hens: 'Active hen records', production: 'Production logs',
        environment: 'Environmental logs', feed: 'Feed consumption logs', mortality: 'Mortality records', alerts: 'Alerts'
    };
    function addSummary(label, value) {
        const row = document.createElement('div');
        row.className = 'flex justify-between gap-4';
        const term = document.createElement('dt');
        const detail = document.createElement('dd');
        term.textContent = label;
        detail.className = 'font-semibold text-right';
        detail.textContent = value;
        row.append(term, detail);
        summary.appendChild(row);
    }
    function buildReview() {
        summary.replaceChildren();
        addSummary('Cage', form.dataset.cageCode);
        let counts = {};
        try { counts = JSON.parse(form.dataset.counts || '{}'); } catch (error) { counts = {}; }
        Object.entries(countLabels).forEach(([key, label]) => {
            if (key === 'hens' || key === 'slots') return;
            const preserveName = { production: 'preserve_production', environment: 'preserve_environment', feed: 'preserve_feed', mortality: 'preserve_mortality' }[key];
            const preserve = Boolean(preserveName && form.elements[preserveName]?.checked);
            addSummary(preserve ? `${label} preserved` : `${label} deleted`, `${counts[key] ?? 0}`);
        });
        addSummary('Cage slots', `${counts.slots ?? 0} deleted`);
        const henAction = form.querySelector('[name="hens_action"]:checked')?.value;
        addSummary(henAction === 'delete' ? 'Active hen records deleted' : 'Active hens detached and records kept', `${counts.hens ?? 0}`);
        addSummary('Sensor-equipped slots affected', `${counts.sensorSlots ?? 0}`);
        addSummary('Sensor disposition', form.elements.return_sensors?.checked ? 'Return to spare inventory' : 'Not returned to spare inventory');
    }
    reviewButton.addEventListener('click', () => {
        if (!form.reportValidity()) return;
        buildReview();
        firstActions.classList.add('hidden');
        review.classList.remove('hidden');
        review.hidden = false;
        review.focus();
    });
    backButton.addEventListener('click', () => {
        review.classList.add('hidden');
        review.hidden = true;
        firstActions.classList.remove('hidden');
        reviewButton.focus();
    });
})();
</script>
@endsection

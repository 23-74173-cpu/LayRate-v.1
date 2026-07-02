@extends('layouts.app')
@section('title', 'Hardware Inventory')

@section('content')
<div class="space-y-5">

    <x-page-header title="Hardware Inventory" subtitle="Manage sensors, relays, and other hardware devices">
        <x-slot:actions>
            <button onclick="openAddModal()"
                    class="flex items-center gap-2 px-6 py-2 text-sm font-medium rounded-full text-white transition-opacity"
                    style="background-color: #0075de;"
                    onmouseover="this.style.opacity='0.85'"
                    onmouseout="this.style.opacity='1'">
                <i data-lucide="plus" class="w-4 h-4"></i> Add Device
            </button>
        </x-slot:actions>
    </x-page-header>

    {{-- Summary Cards --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="rounded-xl border p-4" style="background-color: #ffffff; border-color: #e6e6e6;">
            <div class="text-xs font-semibold tracking-[0.125px] uppercase" style="color: #615d59;">All Active</div>
            <div class="text-[22px] font-bold leading-[1.27] tracking-[-0.25px] mt-1" style="color: #1f1f1f;">{{ $activeCount }}</div>
        </div>
        <div class="rounded-xl border p-4" style="background-color: #ffffff; border-color: #e6e6e6;">
            <div class="text-xs font-semibold tracking-[0.125px] uppercase" style="color: #615d59;">IR Breakbeams</div>
            <div class="text-[22px] font-bold leading-[1.27] tracking-[-0.25px] mt-1" style="color: #2D7D46;">{{ $breakbeamCount }}</div>
        </div>
        <div class="rounded-xl border p-4" style="background-color: #ffffff; border-color: #e6e6e6;">
            <div class="text-xs font-semibold tracking-[0.125px] uppercase" style="color: #615d59;">DHT22</div>
            <div class="text-[22px] font-bold leading-[1.27] tracking-[-0.25px] mt-1" style="color: #1D4E8F;">{{ $dht22Count }}</div>
        </div>
        <div class="rounded-xl border p-4" style="background-color: #ffffff; border-color: #e6e6e6;">
            <div class="text-xs font-semibold tracking-[0.125px] uppercase" style="color: #615d59;">Faulty</div>
            <div class="text-[22px] font-bold leading-[1.27] tracking-[-0.25px] mt-1" style="color: #9b1c24;">{{ $faultyCount }}</div>
        </div>
    </div>

    {{-- Device Table --}}
    <div class="rounded-xl border" style="background-color: #ffffff; border-color: #e6e6e6;">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="border-b" style="background-color: #f6f5f4; border-color: #e6e6e6;">
                        <th class="text-left text-xs font-semibold tracking-[0.125px] uppercase px-6 py-3" style="color: #615d59;">Device</th>
                        <th class="text-left text-xs font-semibold tracking-[0.125px] uppercase px-6 py-3" style="color: #615d59;">Serial #</th>
                        <th class="text-left text-xs font-semibold tracking-[0.125px] uppercase px-6 py-3" style="color: #615d59;">Assigned To</th>
                        <th class="text-left text-xs font-semibold tracking-[0.125px] uppercase px-6 py-3" style="color: #615d59;">Status</th>
                        <th class="text-left text-xs font-semibold tracking-[0.125px] uppercase px-6 py-3" style="color: #615d59;">Installed</th>
                        <th class="text-left text-xs font-semibold tracking-[0.125px] uppercase px-6 py-3" style="color: #615d59;">Last Cal</th>
                        <th class="px-6 py-3"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($items as $item)
                    @php
                        $typeColors = [
                            'IR_breakbeam' => ['#2D7D46', '#d6f0e3'],
                            'DHT22'        => ['#1D4E8F', '#dcebfa'],
                            'relay'        => ['#C2703E', '#fae3d0'],
                            'other'        => ['#6B4C8A', '#e9e0f5'],
                        ];
                        [$tColor, $tSoft] = $typeColors[$item->device_type] ?? ['#6B7280', '#f0f0f0'];

                        $statusAttrs = match ($item->status) {
                            'active'  => ['label' => 'Active',  'class' => 'bg-emerald-50 text-emerald-700 border-emerald-200'],
                            'spare'   => ['label' => 'Spare',   'class' => 'bg-gray-50 text-gray-500 border-gray-200'],
                            'faulty'  => ['label' => 'Faulty',  'class' => 'bg-red-50 text-red-700 border-red-200'],
                            'removed' => ['label' => 'Removed', 'class' => 'bg-gray-100 text-gray-400 border-gray-200'],
                        };

                        if ($item->cageSlot) {
                            $assignedTo = $item->cageSlot->cage?->cage_code . ' · Slot ' . $item->cageSlot->row_number . '-' . $item->cageSlot->column_number;
                        } elseif ($item->cage) {
                            $assignedTo = $item->cage->cage_code;
                        } else {
                            $assignedTo = '—';
                        }
                    @endphp
                    <tr class="border-b hover:bg-black/[0.02] transition-colors" style="border-color: #e6e6e6;">
                        <td class="px-6 py-3">
                            <span class="text-xs font-semibold px-2.5 py-1 rounded-full" style="background:{{ $tSoft }};color:{{ $tColor }};border:1px solid {{ $tColor }}40;">
                                {{ str_replace('_', ' ', $item->device_type) }}
                            </span>
                        </td>
                        <td class="px-6 py-3 text-sm font-mono" style="color: #1f1f1f;">{{ $item->serial_number }}</td>
                        <td class="px-6 py-3 text-sm" style="color: #31302e;">{{ $assignedTo }}</td>
                        <td class="px-6 py-3">
                            <span class="text-xs px-2 py-0.5 rounded-full border font-medium {{ $statusAttrs['class'] }}">
                                {{ $statusAttrs['label'] }}
                            </span>
                        </td>
                        <td class="px-6 py-3 text-sm font-mono" style="color: #615d59;">{{ $item->installation_date?->format('Y-m-d') ?? '—' }}</td>
                        <td class="px-6 py-3 text-sm font-mono" style="color: #615d59;">{{ $item->last_calibration_date?->format('Y-m-d') ?? '—' }}</td>
                        <td class="px-6 py-3">
                            <div class="flex items-center gap-1">
                                <button onclick="openEditModal({{ $item->id }}, '{{ $item->device_type }}', '{{ addslashes($item->serial_number) }}', {{ $item->cage_id ?? 'null' }}, {{ $item->cage_slot_id ?? 'null' }}, '{{ $item->installation_date?->format('Y-m-d') ?? '' }}', '{{ $item->status }}', '{{ $item->last_calibration_date?->format('Y-m-d') ?? '' }}')"
                                        class="p-1.5 rounded-full hover:bg-black/5 transition-colors" style="color: #a39e98;" aria-label="Edit device">
                                    <i data-lucide="pencil" class="w-3.5 h-3.5"></i>
                                </button>
                                <form method="POST" action="{{ route('hardware.destroy', $item) }}"
                                      data-confirm="Remove this hardware item?" data-confirm-action="Remove">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="p-1.5 rounded-full hover:bg-red-50 transition-colors" style="color: #a39e98;" aria-label="Delete device">
                                        <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="7" class="px-6 py-10 text-center text-sm" style="color: #a39e98;">No hardware items yet. Click "Add Device" to register the first one.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>

{{-- Add Modal --}}
<div id="addModal" class="hidden fixed inset-0 z-50 flex items-center justify-center" role="dialog" aria-modal="true">
    <div class="absolute inset-0" style="background-color: rgba(0,0,0,0.35); backdrop-filter: blur(4px);" onclick="closeAddModal()"></div>
    <div class="relative w-full max-w-md rounded-2xl p-6 max-h-[90vh] overflow-y-auto" style="background-color: #ffffff; box-shadow: rgba(0,0,0,0.01) 0 0.175px 1.041px, rgba(0,0,0,0.02) 0 0 0.8px 2.925px, rgba(0,0,0,0.027) 0 2.025px 7.847px, rgba(0,0,0,0.04) 0 4px 18px, rgba(0,0,0,0.05) 0 23px 52px;">
        <div class="flex items-center justify-between mb-5">
            <h2 class="text-[20px] font-semibold leading-[1.4] tracking-[-0.125px]" style="color: #1f1f1f;">Add Device</h2>
            <button onclick="closeAddModal()" class="p-1.5 rounded-full hover:bg-black/5 transition-colors" aria-label="Close">
                <i data-lucide="x" class="w-5 h-5" style="color: #615d59;"></i>
            </button>
        </div>

        <form method="POST" action="{{ route('hardware.store') }}" onsubmit="loadingButton(this.querySelector('button[type=submit]'), 'Adding\u2026')">
            @csrf
            <div class="space-y-4">
                <div>
                    <label class="block text-xs font-semibold tracking-[0.05em] uppercase mb-1.5" style="color: #615d59;">Device Type</label>
                    <select name="device_type" id="addDeviceType" required onchange="updateAddAssignment()"
                            class="w-full border rounded-lg px-3 py-2.5 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#0075de] focus:ring-offset-1"
                            style="border-color: #e6e6e6; color: #1f1f1f;">
                        <option value="">Select type…</option>
                        <option value="IR_breakbeam">IR Breakbeam</option>
                        <option value="DHT22">DHT22 Temp/Humidity</option>
                        <option value="relay">Relay</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold tracking-[0.05em] uppercase mb-1.5" style="color: #615d59;">Serial Number</label>
                    <input type="text" name="serial_number" required maxlength="100"
                           class="w-full border rounded-lg px-3 py-2.5 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#0075de] focus:ring-offset-1"
                           style="border-color: #e6e6e6; color: #1f1f1f;">
                </div>
                <div id="addCageSlotGroup" class="hidden">
                    <label class="block text-xs font-semibold tracking-[0.05em] uppercase mb-1.5" style="color: #615d59;">Cage Slot</label>
                    <select name="cage_slot_id"
                            class="w-full border rounded-lg px-3 py-2.5 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#0075de] focus:ring-offset-1"
                            style="border-color: #e6e6e6; color: #1f1f1f;">
                        <option value="">Select slot…</option>
                        @foreach($cageSlots as $slot)
                        <option value="{{ $slot->id }}">{{ $slot->cage->cage_code }} · Slot {{ $slot->row_number }}-{{ $slot->column_number }}</option>
                        @endforeach
                    </select>
                </div>
                <div id="addCageGroup" class="hidden">
                    <label class="block text-xs font-semibold tracking-[0.05em] uppercase mb-1.5" style="color: #615d59;">Cage</label>
                    <select name="cage_id"
                            class="w-full border rounded-lg px-3 py-2.5 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#0075de] focus:ring-offset-1"
                            style="border-color: #e6e6e6; color: #1f1f1f;">
                        <option value="">Select cage…</option>
                        @foreach($cages as $cage)
                        <option value="{{ $cage->id }}">{{ $cage->cage_code }} — {{ $cage->location ?: 'No location' }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold tracking-[0.05em] uppercase mb-1.5" style="color: #615d59;">Status</label>
                    <select name="status" required
                            class="w-full border rounded-lg px-3 py-2.5 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#0075de] focus:ring-offset-1"
                            style="border-color: #e6e6e6; color: #1f1f1f;">
                        <option value="active">Active</option>
                        <option value="spare">Spare</option>
                        <option value="faulty">Faulty</option>
                        <option value="removed">Removed</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold tracking-[0.05em] uppercase mb-1.5" style="color: #615d59;">Installation Date <span class="font-normal normal-case tracking-normal" style="color: #a39e98;">(optional)</span></label>
                    <input type="date" name="installation_date"
                           class="w-full border rounded-lg px-3 py-2.5 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#0075de] focus:ring-offset-1"
                           style="border-color: #e6e6e6; color: #1f1f1f;">
                </div>
                <div>
                    <label class="block text-xs font-semibold tracking-[0.05em] uppercase mb-1.5" style="color: #615d59;">Last Calibration <span class="font-normal normal-case tracking-normal" style="color: #a39e98;">(optional)</span></label>
                    <input type="date" name="last_calibration_date"
                           class="w-full border rounded-lg px-3 py-2.5 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#0075de] focus:ring-offset-1"
                           style="border-color: #e6e6e6; color: #1f1f1f;">
                </div>
            </div>

            <div class="flex gap-3 mt-5">
                <button type="button" onclick="closeAddModal()"
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
                    Add Device
                </button>
            </div>
        </form>
    </div>
</div>

{{-- Edit Modal --}}
<div id="editModal" class="hidden fixed inset-0 z-50 flex items-center justify-center" role="dialog" aria-modal="true">
    <div class="absolute inset-0" style="background-color: rgba(0,0,0,0.35); backdrop-filter: blur(4px);" onclick="closeEditModal()"></div>
    <div class="relative w-full max-w-md rounded-2xl p-6 max-h-[90vh] overflow-y-auto" style="background-color: #ffffff; box-shadow: rgba(0,0,0,0.01) 0 0.175px 1.041px, rgba(0,0,0,0.02) 0 0 0.8px 2.925px, rgba(0,0,0,0.027) 0 2.025px 7.847px, rgba(0,0,0,0.04) 0 4px 18px, rgba(0,0,0,0.05) 0 23px 52px;">
        <div class="flex items-center justify-between mb-5">
            <h2 class="text-[20px] font-semibold leading-[1.4] tracking-[-0.125px]" style="color: #1f1f1f;">Edit Device</h2>
            <button onclick="closeEditModal()" class="p-1.5 rounded-full hover:bg-black/5 transition-colors" aria-label="Close">
                <i data-lucide="x" class="w-5 h-5" style="color: #615d59;"></i>
            </button>
        </div>

        <form id="editForm" method="POST" onsubmit="loadingButton(this.querySelector('button[type=submit]'))">
            @csrf @method('PUT')
            <div class="space-y-4">
                <div>
                    <label class="block text-xs font-semibold tracking-[0.05em] uppercase mb-1.5" style="color: #615d59;">Device Type</label>
                    <select name="device_type" id="editDeviceType" required onchange="updateEditAssignment()"
                            class="w-full border rounded-lg px-3 py-2.5 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#0075de] focus:ring-offset-1"
                            style="border-color: #e6e6e6; color: #1f1f1f;">
                        <option value="IR_breakbeam">IR Breakbeam</option>
                        <option value="DHT22">DHT22 Temp/Humidity</option>
                        <option value="relay">Relay</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold tracking-[0.05em] uppercase mb-1.5" style="color: #615d59;">Serial Number</label>
                    <input type="text" name="serial_number" id="editSerial" required maxlength="100"
                           class="w-full border rounded-lg px-3 py-2.5 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#0075de] focus:ring-offset-1"
                           style="border-color: #e6e6e6; color: #1f1f1f;">
                </div>
                <div id="editCageSlotGroup" class="hidden">
                    <label class="block text-xs font-semibold tracking-[0.05em] uppercase mb-1.5" style="color: #615d59;">Cage Slot</label>
                    <select name="cage_slot_id" id="editCageSlot"
                            class="w-full border rounded-lg px-3 py-2.5 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#0075de] focus:ring-offset-1"
                            style="border-color: #e6e6e6; color: #1f1f1f;">
                        <option value="">Select slot…</option>
                        @foreach($cageSlots as $slot)
                        <option value="{{ $slot->id }}">{{ $slot->cage->cage_code }} · Slot {{ $slot->row_number }}-{{ $slot->column_number }}</option>
                        @endforeach
                    </select>
                </div>
                <div id="editCageGroup" class="hidden">
                    <label class="block text-xs font-semibold tracking-[0.05em] uppercase mb-1.5" style="color: #615d59;">Cage</label>
                    <select name="cage_id" id="editCage"
                            class="w-full border rounded-lg px-3 py-2.5 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#0075de] focus:ring-offset-1"
                            style="border-color: #e6e6e6; color: #1f1f1f;">
                        <option value="">Select cage…</option>
                        @foreach($cages as $cage)
                        <option value="{{ $cage->id }}">{{ $cage->cage_code }} — {{ $cage->location ?: 'No location' }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold tracking-[0.05em] uppercase mb-1.5" style="color: #615d59;">Status</label>
                    <select name="status" id="editStatus" required onchange="onEditStatusChange()"
                            class="w-full border rounded-lg px-3 py-2.5 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#0075de] focus:ring-offset-1"
                            style="border-color: #e6e6e6; color: #1f1f1f;">
                        <option value="active">Active</option>
                        <option value="spare">Spare</option>
                        <option value="faulty">Faulty</option>
                        <option value="removed">Removed</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold tracking-[0.05em] uppercase mb-1.5" style="color: #615d59;">Installation Date <span class="font-normal normal-case tracking-normal" style="color: #a39e98;">(optional)</span></label>
                    <input type="date" name="installation_date" id="editInstallDate"
                           class="w-full border rounded-lg px-3 py-2.5 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#0075de] focus:ring-offset-1"
                           style="border-color: #e6e6e6; color: #1f1f1f;">
                </div>
                <div>
                    <label class="block text-xs font-semibold tracking-[0.05em] uppercase mb-1.5" style="color: #615d59;">Last Calibration <span class="font-normal normal-case tracking-normal" style="color: #a39e98;">(optional)</span></label>
                    <input type="date" name="last_calibration_date" id="editCalDate"
                           class="w-full border rounded-lg px-3 py-2.5 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#0075de] focus:ring-offset-1"
                           style="border-color: #e6e6e6; color: #1f1f1f;">
                </div>
            </div>

            <div class="flex gap-3 mt-5">
                <button type="button" onclick="closeEditModal()"
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
                    Save Changes
                </button>
            </div>
        </form>
    </div>
</div>

<x-confirm-modal />
@endsection

@push('scripts')
<script>
function updateAddAssignment() {
    var type = document.getElementById('addDeviceType').value;
    var slotGroup = document.getElementById('addCageSlotGroup');
    var cageGroup = document.getElementById('addCageGroup');

    slotGroup.classList.toggle('hidden', type !== 'IR_breakbeam');
    cageGroup.classList.toggle('hidden', type !== 'DHT22' && type !== 'relay');
}

function updateEditAssignment() {
    var type = document.getElementById('editDeviceType').value;
    var slotGroup = document.getElementById('editCageSlotGroup');
    var cageGroup = document.getElementById('editCageGroup');

    slotGroup.classList.toggle('hidden', type !== 'IR_breakbeam');
    cageGroup.classList.toggle('hidden', type !== 'DHT22' && type !== 'relay');
}

function onEditStatusChange() {
    var status = document.getElementById('editStatus').value;
    if (status === 'spare') {
        document.getElementById('editCageSlotGroup').classList.add('hidden');
        document.getElementById('editCageGroup').classList.add('hidden');
    } else {
        updateEditAssignment();
    }
}

function openEditModal(id, deviceType, serial, cageId, cageSlotId, installDate, status, calDate) {
    document.getElementById('editForm').action = '/hardware/' + id;
    document.getElementById('editDeviceType').value = deviceType;
    document.getElementById('editSerial').value = serial;
    document.getElementById('editInstallDate').value = installDate;
    document.getElementById('editStatus').value = status;
    document.getElementById('editCalDate').value = calDate;

    if (cageId) {
        document.getElementById('editCage').value = cageId;
    }
    if (cageSlotId) {
        document.getElementById('editCageSlot').value = cageSlotId;
    }

    onEditStatusChange();
    document.getElementById('editModal').style.display = 'flex';
    lucide.createIcons();
}

function openAddModal() {
    document.getElementById('addModal').style.display = 'flex';
    updateAddAssignment();
    lucide.createIcons();
}

function closeAddModal() {
    document.getElementById('addModal').style.display = 'none';
}

function closeEditModal() {
    document.getElementById('editModal').style.display = 'none';
}

(function() {
    if (window.__hardwareEscapeBound) return;
    window.__hardwareEscapeBound = true;
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeAddModal();
            closeEditModal();
        }
    });
})();
</script>
@endpush

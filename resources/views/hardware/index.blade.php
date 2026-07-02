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

    <turbo-frame id="hardware-live-data" src="{{ route('hardware.live-data') }}" loading="lazy">
        @include('hardware._live-data-skeleton')
    </turbo-frame>

</div>

{{-- Add Modal --}}
<div id="addModal" class="hidden fixed inset-0 z-50 min-h-screen min-h-[100dvh] flex items-center justify-center p-4" role="dialog" aria-modal="true">
    <div class="absolute inset-0 h-full min-h-screen min-h-[100dvh]" style="background-color: rgba(0,0,0,0.35); backdrop-filter: blur(4px);" onclick="closeAddModal()"></div>
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
                        <option value="{{ $cage->id }}">{{ $cage->cage_code }} — {{ $cage->formatted_location }}</option>
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
<div id="editModal" class="hidden fixed inset-0 z-50 min-h-screen min-h-[100dvh] flex items-center justify-center p-4" role="dialog" aria-modal="true">
    <div class="absolute inset-0 h-full min-h-screen min-h-[100dvh]" style="background-color: rgba(0,0,0,0.35); backdrop-filter: blur(4px);" onclick="closeEditModal()"></div>
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
                        <option value="{{ $cage->id }}">{{ $cage->cage_code }} — {{ $cage->formatted_location }}</option>
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

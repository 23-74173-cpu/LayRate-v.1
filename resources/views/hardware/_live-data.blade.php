<turbo-frame id="hardware-live-data">
    {{-- Summary Cards --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
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
</turbo-frame>

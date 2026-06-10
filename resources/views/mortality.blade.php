@extends('layouts.app')
@section('title', 'Mortality Log')

@section('content')
<main class="p-5 space-y-5">

    {{-- ── Header ── --}}
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-lg font-semibold text-[#333333]">Mortality Log</h1>
            <p class="text-xs text-[#6B7280] mt-0.5">Record and track hen mortality per cage</p>
        </div>
        <span class="text-[11px] px-3 py-1.5 rounded-full bg-[#F8D7DA] text-[#721C24] border border-[#F5C6CB]">
            {{ $todayTotal }} recorded today
        </span>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-5">

        {{-- ── Record Form ── --}}
        <div class="bg-white rounded-lg border border-[#D9D9D9] p-5">
            <h2 class="text-sm font-medium text-[#333333] mb-4 flex items-center gap-2">
                <i data-lucide="plus-circle" class="w-4 h-4 text-[#6B7280]"></i>
                Record Mortality
            </h2>

            <form action="{{ route('mortality.store') }}" method="POST" class="space-y-4">
                @csrf

                {{-- Cage --}}
                <div>
                    <label class="block text-[11px] tracking-wider text-[#6B7280] mb-1.5">CAGE</label>
                    <select name="cage_id" required
                            class="w-full border border-[#D9D9D9] rounded-lg px-3 py-2.5 text-sm bg-white text-[#333333] focus:outline-none focus:ring-2 focus:ring-[#102A4C]/30 focus:border-[#102A4C]">
                        <option value="">Select cage…</option>
                        @foreach($cages as $cage)
                        <option value="{{ $cage->id }}" {{ old('cage_id') == $cage->id ? 'selected' : '' }}>
                            {{ $cage->cage_code }} — {{ $cage->location }}
                        </option>
                        @endforeach
                    </select>
                    @error('cage_id')<p class="text-[11px] text-red-500 mt-1">{{ $message }}</p>@enderror
                </div>

                {{-- Date --}}
                <div>
                    <label class="block text-[11px] tracking-wider text-[#6B7280] mb-1.5">DATE</label>
                    <input type="date" name="log_date" required
                           value="{{ old('log_date', date('Y-m-d')) }}"
                           class="w-full border border-[#D9D9D9] rounded-lg px-3 py-2.5 text-sm text-[#333333] focus:outline-none focus:ring-2 focus:ring-[#102A4C]/30 focus:border-[#102A4C]">
                    @error('log_date')<p class="text-[11px] text-red-500 mt-1">{{ $message }}</p>@enderror
                </div>

                {{-- Count --}}
                <div>
                    <label class="block text-[11px] tracking-wider text-[#6B7280] mb-1.5">NUMBER OF DEATHS</label>
                    <input type="number" name="count" min="1" required
                           value="{{ old('count', 1) }}"
                           class="w-full border border-[#D9D9D9] rounded-lg px-3 py-2.5 text-sm text-[#333333] focus:outline-none focus:ring-2 focus:ring-[#102A4C]/30 focus:border-[#102A4C]">
                    @error('count')<p class="text-[11px] text-red-500 mt-1">{{ $message }}</p>@enderror
                </div>

                {{-- Reason dropdown --}}
                <div>
                    <label class="block text-[11px] tracking-wider text-[#6B7280] mb-1.5">CAUSE OF DEATH</label>
                    <select name="reason" required id="reasonSelect"
                            class="w-full border border-[#D9D9D9] rounded-lg px-3 py-2.5 text-sm bg-white text-[#333333] focus:outline-none focus:ring-2 focus:ring-[#102A4C]/30 focus:border-[#102A4C]">
                        <option value="">Select reason…</option>
                        @foreach(\App\Models\MortalityLog::REASONS as $reason)
                        <option value="{{ $reason }}" {{ old('reason') === $reason ? 'selected' : '' }}>
                            {{ $reason }}
                        </option>
                        @endforeach
                    </select>
                    @error('reason')<p class="text-[11px] text-red-500 mt-1">{{ $message }}</p>@enderror
                </div>

                {{-- Notes (always visible, below reason) --}}
                <div>
                    <label class="block text-[11px] tracking-wider text-[#6B7280] mb-1.5">ADDITIONAL NOTES</label>
                    <textarea name="notes" rows="3"
                              placeholder="Describe symptoms, location in cage, or any observations…"
                              class="w-full border border-[#D9D9D9] rounded-lg px-3 py-2.5 text-sm text-[#333333] resize-none focus:outline-none focus:ring-2 focus:ring-[#102A4C]/30 focus:border-[#102A4C]">{{ old('notes') }}</textarea>
                    @error('notes')<p class="text-[11px] text-red-500 mt-1">{{ $message }}</p>@enderror
                </div>

                <button type="submit"
                        class="w-full bg-[#102A4C] text-white py-2.5 rounded-lg text-sm font-medium hover:bg-[#1D4E8F] transition-colors">
                    Save Record
                </button>
            </form>
        </div>

        {{-- ── Today's Summary + Recent Log ── --}}
        <div class="xl:col-span-2 space-y-5">

            {{-- Today's totals per cage --}}
            <div class="bg-white rounded-lg border border-[#D9D9D9] p-5">
                <h2 class="text-sm font-medium text-[#333333] mb-3">Today's Summary</h2>
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
                    @foreach($cages as $cage)
                    @php
                        $count = $todayByCage[$cage->cage_code] ?? 0;
                        $color = $cage->color;
                        $bg    = $count > 0 ? '#F8D7DA' : '#F5F6F8';
                        $txt   = $count > 0 ? '#721C24' : '#6B7280';
                    @endphp
                    <div class="rounded-lg border p-3" style="border-color:{{ $count > 0 ? '#F5C6CB' : '#D9D9D9' }};background:{{ $bg }}">
                        <div class="text-[10px] tracking-wider mb-1" style="color:{{ $color }}">{{ $cage->cage_code }}</div>
                        <div class="text-2xl font-semibold" style="color:{{ $txt }}">{{ $count }}</div>
                        <div class="text-[10px] mt-1" style="color:{{ $txt }}">{{ $count === 1 ? 'hen' : 'hens' }}</div>
                    </div>
                    @endforeach
                </div>
            </div>

            {{-- Recent log table --}}
            <div class="bg-white rounded-lg border border-[#D9D9D9] p-5">
                <h2 class="text-sm font-medium text-[#333333] mb-3">Recent Records</h2>
                @if($logs->isEmpty())
                <div class="py-8 text-center text-sm text-[#6B7280]">No mortality records yet.</div>
                @else
                <div class="overflow-x-auto">
                    <table class="w-full text-xs">
                        <thead>
                            <tr class="border-b border-[#D9D9D9]">
                                <th class="text-left py-2 pr-3 text-[10px] tracking-wider text-[#6B7280] font-medium">DATE</th>
                                <th class="text-left py-2 pr-3 text-[10px] tracking-wider text-[#6B7280] font-medium">CAGE</th>
                                <th class="text-left py-2 pr-3 text-[10px] tracking-wider text-[#6B7280] font-medium">COUNT</th>
                                <th class="text-left py-2 pr-3 text-[10px] tracking-wider text-[#6B7280] font-medium">REASON</th>
                                <th class="text-left py-2 pr-3 text-[10px] tracking-wider text-[#6B7280] font-medium">NOTES</th>
                                <th class="py-2 text-[10px] tracking-wider text-[#6B7280] font-medium"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#F5F6F8]">
                            @foreach($logs as $log)
                            @php
                                $reasonColors = [
                                    'Disease'    => ['#F8D7DA','#721C24'],
                                    'Heat Stress'=> ['#FFF3CD','#856404'],
                                    'Injury'     => ['#FFF3CD','#856404'],
                                    'Predator'   => ['#F8D7DA','#721C24'],
                                    'Unknown'    => ['#F5F6F8','#6B7280'],
                                    'Other'      => ['#F5F6F8','#6B7280'],
                                ];
                                [$rBg,$rTxt] = $reasonColors[$log->reason] ?? ['#F5F6F8','#6B7280'];
                            @endphp
                            <tr class="hover:bg-[#F5F6F8]/50">
                                <td class="py-2.5 pr-3 text-[#6B7280]">{{ $log->log_date->format('M d, Y') }}</td>
                                <td class="py-2.5 pr-3">
                                    <span class="font-medium" style="color:{{ $log->cage->color }}">{{ $log->cage->cage_code }}</span>
                                </td>
                                <td class="py-2.5 pr-3 font-semibold text-[#333333]">{{ $log->count }}</td>
                                <td class="py-2.5 pr-3">
                                    <span class="px-2 py-0.5 rounded text-[10px]" style="background:{{ $rBg }};color:{{ $rTxt }}">
                                        {{ $log->reason }}
                                    </span>
                                </td>
                                <td class="py-2.5 pr-3 text-[#6B7280] max-w-[200px] truncate">
                                    {{ $log->notes ?: '—' }}
                                </td>
                                <td class="py-2.5 text-right">
                                    <form action="{{ route('mortality.destroy', $log) }}" method="POST"
                                          onsubmit="return confirm('Delete this record?')">
                                        @csrf @method('DELETE')
                                        <button class="text-red-400 hover:text-red-600 transition-colors p-1">
                                            <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @endif
            </div>
        </div>
    </div>

</main>
@endsection

@push('scripts')
<script>lucide.createIcons();</script>
@endpush

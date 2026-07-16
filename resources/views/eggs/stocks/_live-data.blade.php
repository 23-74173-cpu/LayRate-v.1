<turbo-frame id="eggs-stocks-live-data">
    {{-- Batch Table --}}
    <div class="bg-white rounded-lg border border-[#D9D9D9] overflow-hidden">
        <table class="w-full">
            <thead>
                <tr class="border-b border-[#D9D9D9] bg-[#F9F9F7]">
                    <th class="text-left text-xs text-[#6B7280] px-5 py-3 font-medium">SIZE</th>
                    <th class="text-left text-xs text-[#6B7280] px-5 py-3 font-medium">COUNT</th>
                    <th class="text-left text-xs text-[#6B7280] px-5 py-3 font-medium">TRAYS</th>
                    <th class="text-left text-xs text-[#6B7280] px-5 py-3 font-medium">HARVESTED</th>
                    <th class="text-left text-xs text-[#6B7280] px-5 py-3 font-medium">SOURCE</th>
                    <th class="text-left text-xs text-[#6B7280] px-5 py-3 font-medium">FRESHNESS</th>
                    <th class="text-left text-xs text-[#6B7280] px-5 py-3 font-medium">ACTIONS</th>
                </tr>
            </thead>
            <tbody id="batchTableBody">
                @forelse($batches as $batch)
                @php
                    $freshnessColors = [
                        'fresh' => ['#e8f5ec', '#1f6b3a', '#cfe8d6'],
                        'aging' => ['#fdf3e0', '#8a5a00', '#f3e3bf'],
                        'old'   => ['#fbe4e6', '#9b1c24', '#f3cdd0'],
                    ];
                    [$fBg, $fTxt, $fBorder] = $freshnessColors[$batch->freshness_status];
                    $cageCode = $batch->cage?->cage_code ?? '—';
                    $cageColor = $batch->cage?->color ?? '#6B7280';
                    $cageSoft = $batch->cage?->color_soft ?? '#f0f0f0';
                    $sizeColors = [
                        'small'    => ['#2D7D46', '#d6f0e3', '#b8e0cc'],
                        'medium'   => ['#1D4E8F', '#dcebfa', '#b3d4fc'],
                        'large'    => ['#C2703E', '#fae3d0', '#f3c9a8'],
                        'jumbo'    => ['#6B4C8A', '#e9e0f5', '#d4c5e8'],
                        'unsorted' => ['#6B7280', '#f0f0f0', '#e0e0e0'],
                    ];
                    [$sBg, $sTxt, $sBorder] = $sizeColors[$batch->egg_size];
                @endphp
                <tr class="border-b border-[#D9D9D9] hover:bg-[#F5F6F8]" data-batch-id="{{ $batch->id }}">
                    <td class="px-5 py-3.5">
                        <span class="px-2.5 py-1 rounded-full text-xs font-semibold" style="background:{{ $sBg }};color:{{ $sTxt }};border:1px solid {{ $sBorder }}">
                            {{ ucfirst($batch->egg_size) }}
                        </span>
                    </td>
                    <td class="px-5 py-3.5 text-sm font-medium text-[#333333]">{{ number_format($batch->count) }}</td>
                    <td class="px-5 py-3.5 text-sm text-[#6B7280]">{{ (int) ceil($batch->count / 30) }}</td>
                    <td class="px-5 py-3.5 text-sm font-mono text-[#333333]">{{ $batch->harvested_date->format('Y-m-d') }}</td>
                    <td class="px-5 py-3.5 text-sm font-medium" style="color:{{ $cageColor }}">{{ $cageCode }}</td>
                    <td class="px-5 py-3.5">
                        <span class="px-2.5 py-1 rounded-full text-xs font-semibold" style="background:{{ $fBg }};color:{{ $fTxt }};border:1px solid {{ $fBorder }}">
                            {{ ucfirst($batch->freshness_status) }}
                        </span>
                    </td>
                    <td class="px-5 py-3.5">
                        <div class="flex items-center gap-2">
                            <button onclick="openEditStock({{ $batch->id }}, '{{ $batch->egg_size }}', {{ $batch->count }}, '{{ $batch->harvested_date->format('Y-m-d') }}')"
                                    class="p-1.5 rounded-full hover:bg-black/5 transition-colors" style="color: #a39e98;" aria-label="Edit stock">
                                <i data-lucide="pencil" class="w-3.5 h-3.5"></i>
                            </button>
                            <a href="{{ route('eggs.stocks.qr', $batch) }}" target="_blank"
                               class="p-1.5 rounded-full hover:bg-black/5 transition-colors" style="color: #a39e98;" aria-label="View QR code">
                                <i data-lucide="qr-code" class="w-3.5 h-3.5"></i>
                            </a>
                            <form method="POST" action="{{ route('eggs.stocks.destroy', $batch) }}"
                                  data-confirm="Delete this stock batch?" data-confirm-action="Delete">
                                @csrf @method('DELETE')
                                <button type="submit" class="p-1.5 rounded-full hover:bg-red-50 transition-colors" style="color: #a39e98;" aria-label="Delete stock">
                                    <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr><td colspan="7" class="px-5 py-10 text-center text-sm text-[#6B7280]">No stock batches yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</turbo-frame>

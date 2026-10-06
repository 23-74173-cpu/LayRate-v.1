<turbo-frame id="eggs-stocks-live-data">
    {{-- Active filters + result line (server-rendered: always matches rows). --}}
    <div class="mb-3">
        <x-filter-chips :chips="$filterChips ?? []" :first="$batches->firstItem()" :last="$batches->lastItem()" :filtered="$batches->total()" :total="$totalBatches ?? null" note="Summary cards and available pools above show all batches, not just the filtered rows." />
    </div>
    {{-- Batch Table --}}
    <div class="bg-white rounded-lg border border-[#D9D9D9] overflow-hidden">
        <div class="overflow-x-auto">
        <table class="w-full">
            <thead>
                <tr class="border-b border-[#D9D9D9] bg-[#F9F9F7]">
                    <th class="px-3 py-3 w-8" aria-label="Select batch"></th>
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
                    $cageCode = $batch->cage?->cage_code ?? '—';
                    $cageColor = $batch->cage?->color ?? '#6B7280';
                    $cageSoft = $batch->cage?->color_soft ?? '#f0f0f0';
                    $sizeColors = [
                        'small'    => ['#2D7D46', '#d6f0e3', '#b8e0cc'],
                        'medium'   => ['var(--color-info)', '#dcebfa', '#b3d4fc'],
                        'large'    => ['#C2703E', '#fae3d0', '#f3c9a8'],
                        'xl'       => ['var(--color-teal-600)', '#d3f0ec', '#a8ddd5'],
                        'jumbo'    => ['#6B4C8A', '#e9e0f5', '#d4c5e8'],
                        'unsorted' => ['#6B7280', '#f0f0f0', '#e0e0e0'],
                    ];
                    [$sBg, $sTxt, $sBorder] = $sizeColors[$batch->egg_size];
                @endphp
                <tr class="border-b border-[#D9D9D9] hover:bg-[#F5F6F8]" data-batch-id="{{ $batch->id }}">
                    <td class="pl-3 py-3.5">
                        <input type="checkbox" class="batch-select shrink-0" value="{{ $batch->id }}" aria-label="Select batch #{{ $batch->id }} for printing">
                    </td>
                    <td class="px-5 py-3.5">
                        <span class="px-2.5 py-1 rounded-full text-xs font-semibold" style="background:{{ $sBg }};color:{{ $sTxt }};border:1px solid {{ $sBorder }}"
                              data-filter-link data-param="size" data-value="{{ $batch->egg_size }}" tabindex="0" role="button"
                              title="Show only {{ \App\Enums\EggSize::labelFor($batch->egg_size) }} batches">
                            {{ \App\Enums\EggSize::labelFor($batch->egg_size) }}
                        </span>
                    </td>
                    <td class="px-5 py-3.5 text-sm font-medium text-[#333333]">{{ number_format($batch->count) }}</td>
                    <td class="px-5 py-3.5 text-sm text-[#6B7280]">{{ (int) ceil($batch->count / 30) }}</td>
                    <td class="px-5 py-3.5 text-sm font-mono text-[#333333]">{{ $batch->harvested_date->format('m/d/Y') }}</td>
                    <td class="px-5 py-3.5 text-sm font-medium" style="color:{{ $cageColor }}"
                        @if($batch->cage_id) data-filter-link data-param="cage_id" data-value="{{ $batch->cage_id }}" tabindex="0" role="button" title="Show only {{ $cageCode }} batches" @endif>{{ $cageCode }}</td>
                    <td class="px-5 py-3.5">
                        <span data-filter-link data-param="freshness" data-value="{{ $batch->freshness_status }}" tabindex="0" role="button" title="Show only {{ $batch->freshness_status }} batches" class="inline-block">
                            <x-status-badge :status="$batch->freshness_status" type="freshness" />
                        </span>
                    </td>
                    <td class="px-5 py-3.5">
                        <div class="flex items-center gap-2">
                            <x-icon-button icon="pencil" label="Edit stock" color="neutral"
                                onclick="openEditStock({{ $batch->id }}, '{{ $batch->egg_size }}', {{ $batch->count }}, '{{ $batch->harvested_date->format('Y-m-d') }}')" />
                            <a href="{{ route('eggs.stocks.qr', $batch) }}" target="_blank"
                               class="p-1.5 rounded-full hover:bg-black/5 transition-colors" style="color: #a39e98;" aria-label="View QR code">
                                <i data-lucide="qr-code" class="w-3.5 h-3.5"></i>
                            </a>
                            <form method="POST" action="{{ route('eggs.stocks.destroy', $batch) }}"
                                  data-confirm="Delete this stock batch?" data-confirm-action="Delete" data-confirm-severity="destructive">
                                @csrf @method('DELETE')
                                <x-icon-button type="submit" icon="trash-2" label="Delete stock" color="red" />
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr><td colspan="8" class="px-5 py-10 text-center text-sm text-[#6B7280]">No stock batches yet.</td></tr>
                @endforelse
                @if($batches->count() > 0)
                {{-- Blank filler rows so a short last page still holds the full
                     table height (perPage rows). Filled rows reduce these blanks
                     as real batches are added. --}}
                @for($i = 0; $i < $batches->perPage() - $batches->count(); $i++)
                <tr class="empty-filler-row" aria-hidden="true">
                    <td colspan="8" class="px-5 py-3.5">&nbsp;</td>
                </tr>
                @endfor
                @endif
            </tbody>
        </table>
        </div>
        <x-paginator :paginator="$batches" />
    </div>
</turbo-frame>

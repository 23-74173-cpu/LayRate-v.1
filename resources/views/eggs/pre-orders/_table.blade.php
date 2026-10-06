<turbo-frame id="eggs-preorders-table">
    {{-- Active filters + result line (server-rendered: always matches rows). --}}
    <div class="mb-3">
        <x-filter-chips :chips="$filterChips ?? []" :first="$orders->firstItem()" :last="$orders->lastItem()" :filtered="$orders->total()" :total="$totalOrders ?? null" note="Availability cards above reflect all pre-orders, not just the filtered rows." />
    </div>
    <div class="bg-white rounded-lg border border-[#D9D9D9] overflow-hidden">
        <div class="overflow-x-auto">
        <table class="w-full">
            <thead>
                <tr class="border-b border-[#D9D9D9] bg-[#F9F9F7]">
                    <th class="text-left text-xs text-[#6B7280] px-5 py-3 font-medium">CUSTOMER</th>
                    <th class="text-left text-xs text-[#6B7280] px-5 py-3 font-medium">SIZE</th>
                    <th class="text-left text-xs text-[#6B7280] px-5 py-3 font-medium">EGGS</th>
                    <th class="text-left text-xs text-[#6B7280] px-5 py-3 font-medium">QTY</th>
                    <th class="text-left text-xs text-[#6B7280] px-5 py-3 font-medium">REQUESTED</th>
                    <th class="text-left text-xs text-[#6B7280] px-5 py-3 font-medium">FULFILLED</th>
                    <th class="text-left text-xs text-[#6B7280] px-5 py-3 font-medium">STATUS</th>
                    <th class="text-right text-xs text-[#6B7280] px-5 py-3 font-medium tabular-nums">TOTAL</th>
                    <th class="text-left text-xs text-[#6B7280] px-5 py-3 font-medium">ACTIONS</th>
                </tr>
            </thead>
            <tbody>
                @forelse($orders as $order)
                @php
                    $sizeColors = [
                        'small'  => ['#2D7D46', '#d6f0e3', '#b8e0cc'],
                        'medium' => ['var(--color-info)', '#dcebfa', '#b3d4fc'],
                        'large'  => ['#C2703E', '#fae3d0', '#f3c9a8'],
                        'xl'     => ['var(--color-teal-600)', '#d3f0ec', '#a8ddd5'],
                        'jumbo'  => ['#6B4C8A', '#e9e0f5', '#d4c5e8'],
                    ];
                    [$szBg, $szTxt, $szBorder] = $sizeColors[$order->egg_size];
                @endphp
                <tr class="border-b border-[#D9D9D9] hover:bg-[#F5F6F8]">
                    <td class="px-5 py-3.5 text-sm font-medium text-[#333333]">{{ $order->customer_name }}</td>
                    <td class="px-5 py-3.5">
                        <span class="px-2.5 py-1 rounded-full text-xs font-semibold" style="background:{{ $szBg }};color:{{ $szTxt }};border:1px solid {{ $szBorder }}"
                              data-filter-link data-param="size" data-value="{{ $order->egg_size }}" tabindex="0" role="button"
                              title="Show only {{ \App\Enums\EggSize::labelFor($order->egg_size) }} orders">
                            {{ \App\Enums\EggSize::labelFor($order->egg_size) }}
                        </span>
                    </td>
                    <td class="px-5 py-3.5 text-sm font-medium text-[#333333]">{{ number_format($order->egg_count) }}</td>
                    <td class="px-5 py-3.5 text-sm text-[#6B7280]">{{ $order->egg_label }}</td>
                    <td class="px-5 py-3.5 text-sm font-mono text-[#333333]">{{ $order->requested_date->format('m/d/Y') }}</td>
                    <td class="px-5 py-3.5 text-sm font-mono text-[#6B7280]">
                        {{ $order->fulfillment_date ? $order->fulfillment_date->format('m/d/Y') : 'Pending' }}
                    </td>
                    <td class="px-5 py-3.5">
                        <span data-filter-link data-param="status" data-value="{{ $order->status }}" tabindex="0" role="button" title="Show only {{ $order->status }} orders" class="inline-block">
                            <x-status-badge :status="$order->status" type="general" />
                        </span>
                    </td>
                    <td class="px-5 py-3.5 text-sm font-medium text-right tabular-nums" style="color: #333333;">
                        {{ $order->total_amount !== null ? '₱' . number_format((float) $order->total_amount, 2) : '—' }}
                        @if($order->payment_status === 'paid')
                        <span class="block mt-1">
                            <span class="inline-block px-2 py-0.5 rounded-full text-xs font-semibold" style="background-color: #e8f5ec; color: #1f6b3a; border: 1px solid #cfe8d6;"
                                  title="Paid {{ $order->paid_at?->format('m/d/Y') }}">Paid</span>
                        </span>
                        @endif
                    </td>
                    <td class="px-5 py-3.5">
                        <div class="flex items-center gap-2">
                            <x-icon-button icon="pencil" label="Edit pre-order" color="neutral"
                                onclick="openEditStatus({{ $order->id }}, '{{ $order->status }}', '{{ $order->fulfillment_date?->toDateString() ?? '' }}', '{{ addslashes($order->customer_name) }}', '{{ $order->egg_size }}', {{ $order->egg_count }}, '{{ $order->requested_date->toDateString() }}', '{{ addslashes($order->notes ?? '') }}', '{{ $order->total_amount !== null ? '₱' . number_format((float) $order->total_amount, 2) : '—' }}', '{{ $order->payment_status === 'paid' ? ($order->paid_at?->format('m/d/Y') ?? 'yes') : '' }}')" />
                            @can('admin')
                            <form action="{{ route('eggs.preorders.destroy', $order) }}" method="POST" data-turbo="false"
                                  data-confirm="Cancel this pre-order?" data-confirm-action="Cancel" data-confirm-severity="destructive">
                                @csrf @method('DELETE')
                                <x-icon-button type="submit" icon="trash-2" label="Cancel pre-order" color="red" />
                            </form>
                            @endcan
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" class="px-5 py-10 text-center text-sm text-[#6B7280]">
                        @if(!empty($hasActiveFilters))
                        No pre-orders match these filters.
                        <button type="button" data-filter-clear-all
                                class="block mx-auto mt-2 text-xs font-medium underline underline-offset-2 hover:brightness-90"
                                style="color: var(--color-navy);">Clear filters</button>
                        @else
                        No pre-orders yet.
                        @endif
                    </td>
                </tr>
                @endforelse
                @if($orders->count() > 0)
                {{-- Blank filler rows to keep the table at a consistent height (perPage rows). --}}
                @for($i = 0; $i < $orders->perPage() - $orders->count(); $i++)
                <tr class="empty-filler-row" aria-hidden="true">
                    <td colspan="8" class="px-5 py-3.5">&nbsp;</td>
                </tr>
                @endfor
                @endif
            </tbody>
        </table>
        </div>
        <x-paginator :paginator="$orders" />
    </div>
</turbo-frame>

<turbo-frame id="eggs-preorders-table">
    <div class="bg-white rounded-lg border border-[#D9D9D9] overflow-hidden">
        <div class="overflow-x-auto">
        <table class="w-full">
            <thead>
                <tr class="border-b border-[#D9D9D9] bg-[#F9F9F7]">
                    <th class="text-left text-xs text-[#6B7280] px-5 py-3 font-medium">CUSTOMER</th>
                    <th class="text-left text-xs text-[#6B7280] px-5 py-3 font-medium">REFERENCE</th>
                    <th class="text-left text-xs text-[#6B7280] px-5 py-3 font-medium">SIZE</th>
                    <th class="text-left text-xs text-[#6B7280] px-5 py-3 font-medium">EGGS</th>
                    <th class="text-left text-xs text-[#6B7280] px-5 py-3 font-medium">QTY</th>
                    <th class="text-left text-xs text-[#6B7280] px-5 py-3 font-medium">REQUESTED</th>
                    <th class="text-left text-xs text-[#6B7280] px-5 py-3 font-medium">FULFILLED</th>
                    <th class="text-left text-xs text-[#6B7280] px-5 py-3 font-medium">STATUS</th>
                    <th class="text-left text-xs text-[#6B7280] px-5 py-3 font-medium">ACTIONS</th>
                </tr>
            </thead>
            <tbody>
                @forelse($orders as $order)
                @php
                    $sizeColors = [
                        'small'  => ['#2D7D46', '#d6f0e3', '#b8e0cc'],
                        'medium' => ['#1D4E8F', '#dcebfa', '#b3d4fc'],
                        'large'  => ['#C2703E', '#fae3d0', '#f3c9a8'],
                        'jumbo'  => ['#6B4C8A', '#e9e0f5', '#d4c5e8'],
                    ];
                    [$szBg, $szTxt, $szBorder] = $sizeColors[$order->egg_size];
                @endphp
                <tr class="border-b border-[#D9D9D9] hover:bg-[#F5F6F8]">
                    <td class="px-5 py-3.5 text-sm font-medium text-[#333333]">{{ $order->customer_name }}</td>
                    <td class="px-5 py-3.5 text-sm text-[#6B7280]">{{ $order->customer_reference ?: '—' }}</td>
                    <td class="px-5 py-3.5">
                        <span class="px-2.5 py-1 rounded-full text-xs font-semibold" style="background:{{ $szBg }};color:{{ $szTxt }};border:1px solid {{ $szBorder }}">
                            {{ ucfirst($order->egg_size) }}
                        </span>
                    </td>
                    <td class="px-5 py-3.5 text-sm font-medium text-[#333333]">{{ number_format($order->egg_count) }}</td>
                    <td class="px-5 py-3.5 text-sm text-[#6B7280]">{{ $order->egg_label }}</td>
                    <td class="px-5 py-3.5 text-sm font-mono text-[#333333]">{{ $order->requested_date->format('Y-m-d') }}</td>
                    <td class="px-5 py-3.5 text-sm font-mono text-[#6B7280]">
                        {{ $order->fulfillment_date ? $order->fulfillment_date->format('Y-m-d') : 'Pending' }}
                    </td>
                    <td class="px-5 py-3.5">
                        <x-status-badge :status="$order->status" type="general" />
                    </td>
                    <td class="px-5 py-3.5">
                        <div class="flex items-center gap-2">
                            <x-icon-button icon="pencil" label="Edit status" color="neutral"
                                onclick="openEditStatus({{ $order->id }}, '{{ $order->status }}', '{{ $order->fulfillment_date?->toDateString() ?? '' }}')" />
                            @can('admin')
                            <form action="{{ route('eggs.preorders.destroy', $order) }}" method="POST"
                                  data-confirm="Cancel this pre-order?" data-confirm-action="Cancel" data-confirm-severity="destructive">
                                @csrf @method('DELETE')
                                <x-icon-button type="submit" icon="trash-2" label="Cancel pre-order" color="red" />
                            </form>
                            @endcan
                        </div>
                    </td>
                </tr>
                @empty
                <tr><td colspan="9" class="px-5 py-10 text-center text-sm text-[#6B7280]">No pre-orders yet.</td></tr>
                @endforelse
            </tbody>
        </table>
        </div>
        <x-paginator :paginator="$orders" />
    </div>
</turbo-frame>

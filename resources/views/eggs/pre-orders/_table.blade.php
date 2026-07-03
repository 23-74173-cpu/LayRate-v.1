<turbo-frame id="eggs-preorders-table">
    <div class="bg-white rounded-lg border border-[#D9D9D9] overflow-hidden">
        <table class="w-full">
            <thead>
                <tr class="border-b border-[#D9D9D9] bg-[#F9F9F7]">
                    <th class="text-left text-xs text-[#6B7280] px-5 py-3 font-medium">CUSTOMER</th>
                    <th class="text-left text-xs text-[#6B7280] px-5 py-3 font-medium">REFERENCE</th>
                    <th class="text-left text-xs text-[#6B7280] px-5 py-3 font-medium">SIZE</th>
                    <th class="text-left text-xs text-[#6B7280] px-5 py-3 font-medium">EGGS</th>
                    <th class="text-left text-xs text-[#6B7280] px-5 py-3 font-medium">TRAYS</th>
                    <th class="text-left text-xs text-[#6B7280] px-5 py-3 font-medium">REQUESTED</th>
                    <th class="text-left text-xs text-[#6B7280] px-5 py-3 font-medium">FULFILLED</th>
                    <th class="text-left text-xs text-[#6B7280] px-5 py-3 font-medium">STATUS</th>
                    <th class="text-left text-xs text-[#6B7280] px-5 py-3 font-medium">ACTIONS</th>
                </tr>
            </thead>
            <tbody>
                @forelse($orders as $order)
                @php
                    $statusColors = [
                        'pending'   => ['#fdf3e0', '#8a5a00', '#f3e3bf'],
                        'fulfilled' => ['#e8f5ec', '#1f6b3a', '#cfe8d6'],
                        'cancelled' => ['#f0f0f0', '#615d59', '#e6e6e6'],
                    ];
                    [$sBg, $sTxt, $sBorder] = $statusColors[$order->status];
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
                    <td class="px-5 py-3.5 text-sm text-[#6B7280]">{{ $order->tray_count }}</td>
                    <td class="px-5 py-3.5 text-sm font-mono text-[#333333]">{{ $order->requested_date->format('Y-m-d') }}</td>
                    <td class="px-5 py-3.5 text-sm font-mono text-[#6B7280]">
                        {{ $order->fulfillment_date ? $order->fulfillment_date->format('Y-m-d') : 'Pending' }}
                    </td>
                    <td class="px-5 py-3.5">
                        <span class="px-2.5 py-1 rounded-full text-xs font-semibold" style="background:{{ $sBg }};color:{{ $sTxt }};border:1px solid {{ $sBorder }}">
                            {{ ucfirst($order->status) }}
                        </span>
                    </td>
                    <td class="px-5 py-3.5">
                        <div class="flex items-center gap-2">
                            <button onclick="openEditStatus({{ $order->id }}, '{{ $order->status }}', '{{ $order->fulfillment_date?->toDateString() ?? '' }}')"
                                    class="p-1.5 rounded-full hover:bg-black/5 transition-colors" style="color: #a39e98;" aria-label="Edit status">
                                <i data-lucide="pencil" class="w-3.5 h-3.5"></i>
                            </button>
                            <form action="{{ route('eggs.preorders.destroy', $order) }}" method="POST"
                                  onsubmit="return confirm('Cancel this pre-order?')">
                                @csrf @method('DELETE')
                                <button class="p-1.5 rounded-full hover:bg-red-50 transition-colors" style="color: #a39e98;" aria-label="Cancel pre-order">
                                    <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr><td colspan="9" class="px-5 py-8 text-center text-sm text-[#6B7280]">No pre-orders yet.</td></tr>
                @endforelse
            </tbody>
        </table>
        @if($orders->hasPages())
        <div class="px-5 py-3 border-t border-[#D9D9D9] flex items-center justify-between text-xs text-[#6B7280]">
            <span>Showing {{ $orders->firstItem() }}-{{ $orders->lastItem() }} of {{ $orders->total() }}</span>
            <div class="flex items-center gap-1">
                @if($orders->onFirstPage())
                <span class="px-2 py-1 text-[#9CA3AF]">‹ Prev</span>
                @else
                <a href="{{ $orders->previousPageUrl() }}" class="px-2 py-1 hover:text-[#002D5E]">‹ Prev</a>
                @endif
                @foreach($orders->getUrlRange(1, $orders->lastPage()) as $page => $url)
                    @if($page == $orders->currentPage())
                    <span class="px-2 py-1 font-medium text-[#002D5E]">{{ $page }}</span>
                    @elseif($page >= $orders->currentPage() - 1 && $page <= $orders->currentPage() + 1)
                    <a href="{{ $url }}" class="px-2 py-1 hover:text-[#002D5E]">{{ $page }}</a>
                    @endif
                @endforeach
                @if($orders->hasMorePages())
                <a href="{{ $orders->nextPageUrl() }}" class="px-2 py-1 hover:text-[#002D5E]">Next ›</a>
                @else
                <span class="px-2 py-1 text-[#9CA3AF]">Next ›</span>
                @endif
            </div>
        </div>
        @endif
    </div>
</turbo-frame>

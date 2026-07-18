<turbo-frame id="notifications-table">
    @if(empty($groups))
    <div class="bg-white rounded-lg border border-[#D9D9D9] p-8 text-center text-sm text-[#6B7280]">
        <i data-lucide="bell-off" class="w-8 h-8 mx-auto mb-2 text-[#D9D9D9]"></i>
        No notifications yet.
    </div>
    @else
    <div class="space-y-6">
        @foreach($groups as $category => $alerts)
        <div>
            <h3 class="text-xs font-semibold tracking-[0.05em] uppercase mb-3" style="color: #615d59;">{{ $category }}</h3>
            <div class="space-y-2">
                @foreach($alerts as $alert)
                <div class="flex items-start gap-3 rounded-lg border p-4 {{ $alert->is_read ? 'bg-[#FAFAFA]' : 'bg-white' }}" style="border-color: #D9D9D9;">
                    <span class="w-2 h-2 rounded-full mt-1.5 shrink-0" style="background-color: {{ $alert->cage?->color ?? '#6B7280' }};"></span>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 mb-0.5">
                            @if($alert->cage)
                            <span class="text-xs font-medium" style="color: #31302e;">{{ $alert->cage->cage_code }}</span>
                            @else
                            <span class="text-xs font-medium" style="color: #31302e;">Farm-wide</span>
                            @endif
                            <span class="text-xs" style="color: #a39e98;">· {{ $alert->triggered_at->diffForHumans() }}</span>
                            @if(!$alert->is_read)
                            <span class="w-1.5 h-1.5 rounded-full bg-[#0075de]"></span>
                            @endif
                        </div>
                        <p class="text-sm mt-0.5" style="color: #1f1f1f;">{{ $alert->message }}</p>
                        <div class="flex items-center gap-2 mt-1.5">
                            <span class="text-xs px-2 py-0.5 rounded" style="background-color: #F0F0EC; color: #6B7280;">{{ $alert->alert_type }}</span>
                            @if(!$alert->is_read)
                            <form method="POST" action="{{ route('alerts.read', $alert) }}" class="inline">
                                @csrf
                                <button type="submit" class="text-xs font-medium hover:underline" style="color: #0075de;">Mark read</button>
                            </form>
                            @else
                            <span class="text-xs" style="color: #6B7280;">Read</span>
                            @endif
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
        @endforeach
    </div>
    @endif
</turbo-frame>

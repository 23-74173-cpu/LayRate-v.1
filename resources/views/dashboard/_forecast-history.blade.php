<turbo-frame id="dashboard-forecast-history">
    <div class="bg-white rounded-2xl border border-[#e6e6e6] p-3 h-auto flex flex-col lg:h-full">
        <div class="flex items-start gap-3 mb-3">
            <span class="w-6 h-6 rounded-lg flex items-center justify-center shrink-0" style="background-color: #f0f0ff; color: #6366f1;">
                <i data-lucide="history" class="w-3 h-3"></i>
            </span>
            <div>
                <div class="text-xs font-semibold tracking-[0.125px] uppercase text-[#6B7280]">Forecast History</div>
                <div class="text-[11px]" style="color: #a39e98;">{{ $cageCode ? $cageCode : 'Farm-wide' }} — predicted vs. actual, most recent first</div>
            </div>
        </div>

        @if($history->isEmpty())
        <div class="rounded-xl border py-8 text-center text-sm" style="background-color: #ffffff; border-color: #e6e6e6; color: #a39e98;">
            No forecasts have been generated yet.
        </div>
        @else
        <div class="overflow-x-auto">
            <table class="w-full text-xs">
                <thead>
                    <tr class="text-left" style="color: #a39e98;">
                        <th class="font-medium pb-2 pr-3">Target Date</th>
                        <th class="font-medium pb-2 pr-3">Forecasted On</th>
                        <th class="font-medium pb-2 pr-3 text-right">Predicted</th>
                        <th class="font-medium pb-2 pr-3 text-right">Actual</th>
                        <th class="font-medium pb-2 text-right">Variance</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($history as $row)
                    <tr class="border-t" style="border-color: #f3f4f6;">
                        <td class="py-2 pr-3" style="color: #1f1f1f;">{{ \Carbon\Carbon::parse($row->target_date)->display() }}</td>
                        <td class="py-2 pr-3" style="color: #6B7280;">{{ \Carbon\Carbon::parse($row->forecast_date)->display() }}</td>
                        <td class="py-2 pr-3 text-right font-medium" style="color: #1f1f1f;">{{ number_format($row->predicted) }}</td>
                        <td class="py-2 pr-3 text-right font-medium" style="color: #1f1f1f;">
                            @if($row->pending)
                                <span style="color: #a39e98;">Pending</span>
                            @else
                                {{ number_format($row->actual) }}
                            @endif
                        </td>
                        <td class="py-2 text-right">
                            @if($row->pending)
                                <span style="color: #a39e98;">—</span>
                            @else
                                <span class="font-semibold" style="color: {{ abs($row->variance_pct) < 3 ? '#1f6b3a' : (abs($row->variance_pct) < 10 ? '#8a5a00' : '#9b1c24') }};">
                                    {{ $row->variance_pct > 0 ? '+' : '' }}{{ $row->variance_pct }}%
                                </span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </div>
</turbo-frame>

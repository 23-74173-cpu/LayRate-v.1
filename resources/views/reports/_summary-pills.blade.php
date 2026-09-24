{{-- Report summary as plain "Label: value" text (no scorecard widgets).
     $summary is label => value from ReportController::buildSummary(), which
     already puts the unit on every number, so this preview, the printable
     view, and the PDF all show the same text. --}}
@if(!empty($summary))
<dl class="report-summary grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-1.5 mb-6 text-sm text-[#333333]">
    @foreach($summary as $label => $value)
    <div class="flex flex-wrap gap-1.5">
        <dt class="font-semibold text-[#1f1f1f]">{{ $label }}:</dt>
        <dd>{{ $value }}</dd>
    </div>
    @endforeach
</dl>
@endif

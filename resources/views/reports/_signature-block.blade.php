{{-- Shared by the preview and the printable document — keep both identical. --}}
<div class="signature-block mt-12 pt-6 border-t border-[#D9D9D9] grid grid-cols-2 gap-16">
    <div><div class="text-xs text-[#6B7280] mb-8">Prepared by:</div><div class="border-b border-[#333333] mb-1.5"></div><div class="text-xs text-[#6B7280]">{{ auth()->user()->name }}</div><div class="text-xs text-[#6B7280]">Signature / Date</div></div>
    <div><div class="text-xs text-[#6B7280] mb-8">Noted by:</div><div class="border-b border-[#333333] mb-1.5"></div><div class="text-xs text-[#6B7280]">Name / Position</div><div class="text-xs text-[#6B7280]">Signature / Date</div></div>
</div>

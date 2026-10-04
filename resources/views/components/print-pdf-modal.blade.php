{{--
    <x-print-pdf-modal id="..." title="..." :action="route(...)">extra fields</x-print-pdf-modal>

    Small options dialog for the printable PDF sheets (egg QR labels, hen
    foot tags). A plain GET form that opens the PDF in a new tab, so it works
    without page scripts and the browser's own PDF viewer does the printing.
    Paper sizes come from PrintableTagsController::PAPERS. Open it with
    document.getElementById(id).style.display = 'flex'.
--}}
@props(['id', 'title', 'action', 'description' => null, 'submitLabel' => 'Open PDF'])

<div id="{{ $id }}" data-modal style="display: none;" class="fixed inset-0 z-50 min-h-screen min-h-[100dvh] flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="{{ $id }}-title">
    <div class="absolute inset-0 h-full min-h-screen min-h-[100dvh]" style="background-color: rgba(0,0,0,0.35); backdrop-filter: blur(4px);" onclick="document.getElementById('{{ $id }}').style.display = 'none'"></div>
    <div class="relative w-full max-w-md rounded-2xl p-6 max-h-screen max-h-[100dvh] overflow-y-auto modal-card" style="background-color: #ffffff;">
        <div class="flex items-center justify-between mb-2">
            <h2 id="{{ $id }}-title" class="text-[20px] font-semibold leading-[1.4] tracking-[-0.125px]" style="color: #1f1f1f;">{{ $title }}</h2>
            <button type="button" onclick="document.getElementById('{{ $id }}').style.display = 'none'" class="p-1.5 rounded-full hover:bg-black/5 transition-colors" aria-label="Close">
                <i data-lucide="x" class="w-5 h-5" style="color: #615d59;"></i>
            </button>
        </div>
        @if($description)
            <p class="text-sm mb-5" style="color: #615d59;">{{ $description }}</p>
        @endif

        <form method="GET" action="{{ $action }}" target="_blank" data-turbo="false" data-native-validation
              onsubmit="setTimeout(function () { document.getElementById('{{ $id }}').style.display = 'none'; }, 0)">
            <div class="space-y-4">
                {{ $slot }}

                <div>
                    <label for="{{ $id }}-paper" class="block text-xs font-medium tracking-[0.05em] uppercase mb-1.5" style="color: #615d59;">Paper</label>
                    <select id="{{ $id }}-paper" name="paper" class="w-full border border-[#D9D9D9] rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-navy/30 focus:border-navy">
                        @foreach(\App\Http\Controllers\PrintableTagsController::PAPERS as $key => $paper)
                            <option value="{{ $key }}">{{ $paper['label'] }}</option>
                        @endforeach
                    </select>
                    <p class="text-xs mt-1.5" style="color: #6B7280;">Print at 100% scale ("Actual size"), not "Fit to page", so the labels keep their size.</p>
                </div>
            </div>

            <div class="flex gap-3 mt-6">
                <button type="button" onclick="document.getElementById('{{ $id }}').style.display = 'none'"
                        class="flex-1 px-4 py-2.5 rounded-lg text-sm font-medium border border-[#D9D9D9] text-[#333333] hover:bg-[#F5F6F8] transition-colors">Cancel</button>
                <button type="submit"
                        class="flex-1 inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-lg text-sm font-semibold text-white bg-navy hover:brightness-90 transition">
                    <i data-lucide="printer" class="w-4 h-4"></i> {{ $submitLabel }}
                </button>
            </div>
        </form>
    </div>
</div>

<div id="editLogModal" class="hidden fixed inset-0 z-50 min-h-screen min-h-[100dvh] flex items-center justify-center p-4" style="display: none;" role="dialog" aria-modal="true">
    <div class="absolute inset-0 h-full min-h-screen min-h-[100dvh]" style="background-color: rgba(0,0,0,0.35); backdrop-filter: blur(4px);" onclick="closeEditLogModal()"></div>
    <div class="relative w-full max-w-md rounded-2xl p-6" style="background-color: #ffffff; box-shadow: rgba(0,0,0,0.01) 0 0.175px 1.041px, rgba(0,0,0,0.02) 0 0 0.8px 2.925px, rgba(0,0,0,0.027) 0 2.025px 7.847px, rgba(0,0,0,0.04) 0 4px 18px, rgba(0,0,0,0.05) 0 23px 52px;">
        <div class="flex items-center justify-between mb-5">
            <h2 class="text-[20px] font-semibold leading-[1.4] tracking-[-0.125px]" style="color: #1f1f1f;">Edit Production Log</h2>
            <button onclick="closeEditLogModal()" class="p-1.5 rounded-full hover:bg-black/5 transition-colors" aria-label="Close">
                <i data-lucide="x" class="w-5 h-5" style="color: #615d59;"></i>
            </button>
        </div>

        <form id="editLogForm" method="POST" data-turbo="false" onsubmit="loadingButton(this.querySelector('button[type=submit]'))">
            @csrf @method('PUT')

            <div class="space-y-4">
                <div>
                    <label class="block text-xs font-semibold tracking-[0.05em] uppercase mb-1.5" style="color: #615d59;">Date</label>
                    <input type="date" name="log_date" id="editLogDate" required
                           class="w-full border rounded-lg px-3 py-2.5 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#0075de] focus:ring-offset-1"
                           style="border-color: #e6e6e6; color: #1f1f1f;">
                </div>

                <div>
                    <label class="block text-xs font-semibold tracking-[0.05em] uppercase mb-1.5" style="color: #615d59;">Egg Count</label>
                    <input type="number" name="egg_count" id="editEggCount" min="0" required
                           oninput="editComputeHdep(); editCheckSizeSum()"
                           class="w-full border rounded-lg px-3 py-2.5 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#0075de] focus:ring-offset-1"
                           style="border-color: #e6e6e6; color: #1f1f1f;">
                </div>

                <div>
                    <label class="block text-xs font-semibold tracking-[0.05em] uppercase mb-1.5" style="color: #615d59;">Hen Count <span class="font-normal normal-case tracking-normal" style="color: #a39e98;">(auto-populated from active hens on slot)</span></label>
                    <input type="number" id="editHenCountDisplay" readonly
                           class="w-full border rounded-lg px-3 py-2.5 text-sm bg-gray-50 cursor-not-allowed focus:outline-none"
                           style="border-color: #e6e6e6; color: #615d59;">
                    <div id="editHdepDisplay" class="mt-2 inline-block border rounded-lg px-3 py-1.5 text-sm font-mono" style="background-color: #f6f5f4; border-color: #e6e6e6; color: #1f1f1f;">
                        HDEP: —
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold tracking-[0.05em] uppercase mb-1.5" style="color: #615d59;">Notes <span class="font-normal normal-case tracking-normal" style="color: #a39e98;">(optional)</span></label>
                    <textarea name="notes" id="editNotes" rows="2"
                              class="w-full border rounded-lg px-3 py-2.5 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#0075de] focus:ring-offset-1 resize-y"
                              style="border-color: #e6e6e6; color: #1f1f1f;"></textarea>
                </div>

                {{-- Size Breakdown --}}
                <div class="border-t pt-4" style="border-color: #e6e6e6;">
                    <label class="block text-xs font-semibold tracking-[0.05em] uppercase mb-3" style="color: #615d59;">
                        Size Breakdown
                        <span class="font-normal normal-case tracking-normal" style="color: #a39e98;">(optional)</span>
                    </label>
                    <div class="grid grid-cols-4 gap-2" id="editSizeBreakdown">
                        <div>
                            <label class="block text-xs text-center mb-1" style="color: #2D7D46;">Small</label>
                            <input type="number" name="size_small" id="editSizeSmall" min="0" value="0"
                                   oninput="editCheckSizeSum()"
                                   class="w-full border rounded-lg px-2 py-2 text-sm text-center bg-white focus:outline-none focus:ring-2 focus:ring-[#0075de] focus:ring-offset-1"
                                   style="border-color: #e6e6e6; color: #1f1f1f;">
                        </div>
                        <div>
                            <label class="block text-xs text-center mb-1" style="color: #1D4E8F;">Medium</label>
                            <input type="number" name="size_medium" id="editSizeMedium" min="0" value="0"
                                   oninput="editCheckSizeSum()"
                                   class="w-full border rounded-lg px-2 py-2 text-sm text-center bg-white focus:outline-none focus:ring-2 focus:ring-[#0075de] focus:ring-offset-1"
                                   style="border-color: #e6e6e6; color: #1f1f1f;">
                        </div>
                        <div>
                            <label class="block text-xs text-center mb-1" style="color: #C2703E;">Large</label>
                            <input type="number" name="size_large" id="editSizeLarge" min="0" value="0"
                                   oninput="editCheckSizeSum()"
                                   class="w-full border rounded-lg px-2 py-2 text-sm text-center bg-white focus:outline-none focus:ring-2 focus:ring-[#0075de] focus:ring-offset-1"
                                   style="border-color: #e6e6e6; color: #1f1f1f;">
                        </div>
                        <div>
                            <label class="block text-xs text-center mb-1" style="color: #6B4C8A;">Jumbo</label>
                            <input type="number" name="size_jumbo" id="editSizeJumbo" min="0" value="0"
                                   oninput="editCheckSizeSum()"
                                   class="w-full border rounded-lg px-2 py-2 text-sm text-center bg-white focus:outline-none focus:ring-2 focus:ring-[#0075de] focus:ring-offset-1"
                                   style="border-color: #e6e6e6; color: #1f1f1f;">
                        </div>
                    </div>
                    <div id="editSizeSumMsg" class="mt-2 text-xs" style="color: #a39e98;">Sum: 0</div>
                </div>
            </div>

            <div class="flex gap-3 mt-5">
                <button type="button" onclick="closeEditLogModal()"
                        class="flex-1 py-2.5 text-sm font-medium rounded-lg transition-colors"
                        style="color: #1f1f1f; border: 1px solid #e6e6e6;"
                        onmouseover="this.style.backgroundColor='#f6f5f4'"
                        onmouseout="this.style.backgroundColor='transparent'">
                    Cancel
                </button>
                <x-button type="submit" class="flex-1 py-2.5">
                    Save Changes
                </x-button>
            </div>
        </form>
    </div>
</div>

<script>
function editCheckSizeSum() {
    const totalEggs = parseInt(document.getElementById('editEggCount').value) || 0;
    const inputs = ['editSizeSmall', 'editSizeMedium', 'editSizeLarge', 'editSizeJumbo'];
    let sum = 0;
    inputs.forEach(function(id) { sum += parseInt(document.getElementById(id).value) || 0; });
    const msg = document.getElementById('editSizeSumMsg');
    if (sum === totalEggs) {
        msg.textContent = 'Sum: ' + sum + ' ✓';
        msg.style.color = '#1f6b3a';
    } else {
        msg.textContent = 'Sum: ' + sum + ' (should be ' + totalEggs + ')';
        msg.style.color = '#9b1c24';
    }
}
</script>

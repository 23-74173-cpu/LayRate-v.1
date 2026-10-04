{{--
    Egg prices form partial — shared by the Profile System tab card and the
    Egg Management dock modal. Expects $eggPrices (keyed by size, null = not
    set) and $lastPriceChange (nullable). Posts to settings.egg-prices.update.
--}}
                <form method="POST" action="{{ route('settings.egg-prices.update') }}"
                      onsubmit="var btn=this.querySelector('button[type=submit]');btn.disabled=true;btn.textContent='Saving\u2026';">
                    @csrf @method('PUT')
                    <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b" style="border-color: #e6e6e6;">
                                <th class="text-left text-xs font-medium py-2 pr-3" style="color: #6B7280;">SIZE</th>
                                <th class="text-right text-xs font-medium py-2 pr-3" style="color: #6B7280;">PER TRAY (₱)</th>
                                <th class="text-right text-xs font-medium py-2" style="color: #6B7280;">PER PIECE (₱)</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach(\App\Enums\EggSize::saleValues() as $sz)
                            <tr class="border-b" style="border-color: #f0f0f0;">
                                <td class="py-2 pr-3 font-medium" style="color: #333333;">{{ \App\Enums\EggSize::labelFor($sz) }}</td>
                                <td class="py-2 pr-3">
                                    <div class="flex items-center justify-end gap-1">
                                        <span class="text-xs" style="color: #a39e98;">₱</span>
                                        <input type="number" name="prices[{{ $sz }}][tray]" id="priceTray{{ $sz }}" step="0.01" min="0" max="999999.99" placeholder="—"
                                               value="{{ old("prices.{$sz}.tray", $eggPrices[$sz]->price_per_tray ?? '') }}"
                                               oninput="mirrorPriceFromTray('{{ $sz }}')"
                                               class="w-28 border rounded-lg px-2 py-1.5 text-sm text-right bg-white focus:outline-none focus:ring-2 focus:ring-navy focus:ring-offset-1"
                                               style="border-color: #e6e6e6; color: #1f1f1f;">
                                    </div>
                                    <x-input-error name="prices.{{ $sz }}.tray" />
                                </td>
                                <td class="py-2">
                                    <div class="flex items-center justify-end gap-1">
                                        <span class="text-xs" style="color: #a39e98;">₱</span>
                                        <input type="number" name="prices[{{ $sz }}][piece]" id="pricePiece{{ $sz }}" step="0.01" min="0" max="999999.99" placeholder="—"
                                               value="{{ old("prices.{$sz}.piece", $eggPrices[$sz]->price_per_piece ?? '') }}"
                                               oninput="mirrorPriceFromPiece('{{ $sz }}')"
                                               class="w-28 border rounded-lg px-2 py-1.5 text-sm text-right bg-white focus:outline-none focus:ring-2 focus:ring-navy focus:ring-offset-1"
                                               style="border-color: #e6e6e6; color: #1f1f1f;">
                                    </div>
                                    <x-input-error name="prices.{{ $sz }}.piece" />
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                    </div>
                    <div class="flex items-center justify-between gap-3 mt-4">
                        <p class="text-xs" style="color: #a39e98;">
                            @if(!empty($lastPriceChange))
                            Last updated by {{ $lastPriceChange->changedBy?->name ?? '—' }} on {{ $lastPriceChange->created_at->format('m/d/Y') }}
                            @else
                            Never updated
                            @endif
                        </p>
                        <x-button type="submit" class="px-6 py-2">Save Prices</x-button>
                    </div>
                </form>
                <script>
                // Live tray<->piece conversion, written into the other input.
                // The field being typed in wins; clearing it clears the mirror
                // back to blank ("not set"). Integer-cent math, half-up.
                function mirrorPriceFromTray(sz) {
                    var trayEl = document.getElementById('priceTray' + sz);
                    var pieceEl = document.getElementById('pricePiece' + sz);
                    if (!trayEl || !pieceEl) return;
                    if (trayEl.value === '' || isNaN(parseFloat(trayEl.value))) { pieceEl.value = ''; return; }
                    var trayCents = Math.round(parseFloat(trayEl.value) * 100);
                    pieceEl.value = (Math.round(trayCents / 30) / 100).toFixed(2);
                }
                function mirrorPriceFromPiece(sz) {
                    var trayEl = document.getElementById('priceTray' + sz);
                    var pieceEl = document.getElementById('pricePiece' + sz);
                    if (!trayEl || !pieceEl) return;
                    if (pieceEl.value === '' || isNaN(parseFloat(pieceEl.value))) { trayEl.value = ''; return; }
                    var pieceCents = Math.round(parseFloat(pieceEl.value) * 100);
                    trayEl.value = ((pieceCents * 30) / 100).toFixed(2);
                }
                </script>

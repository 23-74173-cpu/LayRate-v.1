<?php

namespace App\Http\Controllers;

use App\Enums\EggSize;
use App\Models\EggPrice;
use App\Models\EggPriceHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class EggPriceController extends Controller
{
    /**
     * Price form data for the dock modal (same partial as the Profile card).
     * Served through a turbo-frame so egg pages never pay for it unopened.
     */
    public function index()
    {
        return view('settings.egg-prices-modal', [
            'eggPrices' => EggPrice::all()->keyBy('egg_size')->all(),
            'lastPriceChange' => EggPriceHistory::with('changedBy')->latest()->first(),
        ]);
    }

    /**
     * Save the admin-configured egg prices (per size: tray + piece, either
     * blank for "not set"). Every change — including the first entry, whose
     * old values are NULL — is recorded in the history table in the same
     * transaction as the save. Future orders only; snapshots on existing
     * orders are never touched here.
     */
    public function update(Request $request)
    {
        $rules = [];

        foreach (EggSize::saleValues() as $size) {
            $rules["prices.{$size}.tray"] = 'nullable|decimal:0,2|min:0|max:999999.99';
            $rules["prices.{$size}.piece"] = 'nullable|decimal:0,2|min:0|max:999999.99';
        }

        $validator = Validator::make($request->all(), $rules, [], [
            'prices.*.tray' => 'tray price',
            'prices.*.piece' => 'piece price',
        ]);

        // The form also lives in the Egg Management dock modal: validation
        // failures always land back on the Profile System tab, where the
        // same form (and its error slots) is waiting.
        if ($validator->fails()) {
            return redirect()->route('profile', ['tab' => 'system'])
                ->withErrors($validator)
                ->withInput();
        }

        $validated = $validator->validated();

        DB::transaction(function () use ($validated, $request) {
            foreach (EggSize::saleValues() as $size) {
                $tray = $validated['prices'][$size]['tray'] ?? null;
                $piece = $validated['prices'][$size]['piece'] ?? null;
                $tray = $tray === '' ? null : $tray;
                $piece = $piece === '' ? null : $piece;

                $existing = EggPrice::where('egg_size', $size)->first();

                $oldTray = $existing?->price_per_tray;
                $oldPiece = $existing?->price_per_piece;

                if ((string) $oldTray === (string) $tray && (string) $oldPiece === (string) $piece) {
                    continue;
                }

                EggPrice::updateOrCreate(
                    ['egg_size' => $size],
                    [
                        'price_per_tray' => $tray,
                        'price_per_piece' => $piece,
                        'updated_by' => $request->user()->id,
                    ]
                );

                EggPriceHistory::create([
                    'egg_size' => $size,
                    'old_price_per_tray' => $oldTray,
                    'old_price_per_piece' => $oldPiece,
                    'new_price_per_tray' => $tray,
                    'new_price_per_piece' => $piece,
                    'changed_by' => $request->user()->id,
                ]);
            }
        });

        return redirect()->route('profile', ['tab' => 'system'])
            ->with('success', 'Egg prices saved. New prices apply to future orders only.');
    }
}

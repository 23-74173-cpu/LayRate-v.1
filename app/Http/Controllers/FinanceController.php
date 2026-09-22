<?php

namespace App\Http\Controllers;

use App\Models\Cage;
use App\Models\FinanceTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class FinanceController extends Controller
{
    public function index(Request $request)
    {
        $from = $request->get('from') ?: null;
        $to   = $request->get('to') ?: null;
        $type = $request->get('type', 'all');

        $transactions = FinanceTransaction::with('cage')
            ->when($from && $to, fn ($q) => $q->whereBetween('date', [$from, $to]))
            ->when($type !== 'all', fn ($q) => $q->where('type', $type))
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        $summaryQuery = FinanceTransaction::query()
            ->when($from && $to, fn ($q) => $q->whereBetween('date', [$from, $to]));

        $totalIncome = (clone $summaryQuery)->where('type', 'income')->sum('amount');
        $totalExpense = (clone $summaryQuery)->where('type', 'expense')->sum('amount');

        $cages = Cage::orderBy('cage_code')->get();

        return view('finance.index', [
            'transactions' => $transactions,
            'cages'        => $cages,
            'types'        => FinanceTransaction::TYPES,
            'categories'   => FinanceTransaction::CATEGORIES,
            'from'         => $from,
            'to'           => $to,
            'type'         => $type,
            'totalIncome'  => $totalIncome,
            'totalExpense' => $totalExpense,
            'net'          => $totalIncome - $totalExpense,
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'type'        => 'required|in:' . implode(',', FinanceTransaction::TYPES),
            'category'    => 'required|in:' . implode(',', FinanceTransaction::CATEGORIES),
            'amount'      => 'required|numeric|min:0.01',
            'date'        => 'required|date',
            'description' => 'nullable|string|max:1000',
            'cage_id'     => 'nullable|exists:cages,id',
        ]);

        if ($validator->fails()) {
            return redirect()->route('finance.index')
                ->withErrors($validator)
                ->withInput();
        }

        FinanceTransaction::create($validator->validated() + ['recorded_by' => auth()->id()]);

        return redirect()->route('finance.index')->with('success', 'Transaction recorded.');
    }

    public function update(Request $request, FinanceTransaction $financeTransaction)
    {
        $validator = Validator::make($request->all(), [
            'type'        => 'required|in:' . implode(',', FinanceTransaction::TYPES),
            'category'    => 'required|in:' . implode(',', FinanceTransaction::CATEGORIES),
            'amount'      => 'required|numeric|min:0.01',
            'date'        => 'required|date',
            'description' => 'nullable|string|max:1000',
            'cage_id'     => 'nullable|exists:cages,id',
        ]);

        if ($validator->fails()) {
            return redirect()->route('finance.index')
                ->with('reopen_edit_finance', $financeTransaction->id)
                ->withErrors($validator)
                ->withInput();
        }

        $financeTransaction->update($validator->validated());

        return redirect()->route('finance.index')->with('success', 'Transaction updated.');
    }

    public function destroy(FinanceTransaction $financeTransaction)
    {
        $financeTransaction->delete();

        return redirect()->route('finance.index')->with('success', 'Transaction deleted.');
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Cage;
use App\Models\FinanceTransaction;
use App\Services\ReportingDateService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class FinanceController extends Controller
{
    public function index(Request $request)
    {
        $filters = $this->filtersFromRequest($request);

        $filtered = fn () => $this->filteredQuery($filters);

        $transactions = $filtered()
            ->with(['cage', 'recorder'])
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        // Totals and the category breakdown use the same filters as the
        // table, so the numbers on the page always add up to what is listed.
        $totals = $filtered()
            ->selectRaw('type, SUM(amount) as total')
            ->groupBy('type')
            ->pluck('total', 'type');

        $totalIncome  = (float) ($totals['income'] ?? 0);
        $totalExpense = (float) ($totals['expense'] ?? 0);

        $breakdown = $filtered()
            ->selectRaw('type, category, SUM(amount) as total, COUNT(*) as entries')
            ->groupBy('type', 'category')
            ->orderByDesc('total')
            ->get()
            ->groupBy('type');

        return view('finance.index', [
            'transactions' => $transactions,
            'cages'        => Cage::orderBy('cage_code')->get(),
            'filters'      => $filters,
            'totalIncome'  => $totalIncome,
            'totalExpense' => $totalExpense,
            'net'          => $totalIncome - $totalExpense,
            'breakdown'    => $breakdown,
        ]);
    }

    public function store(Request $request)
    {
        $validator = $this->validator($request);

        if ($validator->fails()) {
            return redirect()->route('finance.index')
                ->withErrors($validator)
                ->withInput();
        }

        $transaction = new FinanceTransaction($validator->validated());
        $transaction->recorded_by = auth()->id();
        $transaction->save();

        return redirect()->route('finance.index')->with('success', 'Transaction recorded.');
    }

    public function update(Request $request, FinanceTransaction $financeTransaction)
    {
        $validator = $this->validator($request);

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

    private function validator(Request $request)
    {
        return Validator::make($request->all(), [
            'type'        => ['required', Rule::in(FinanceTransaction::TYPES)],
            // The category has to belong to the chosen type (no "Egg Sales" expense).
            'category'    => ['required', Rule::in(FinanceTransaction::categoriesFor($request->input('type')))],
            'amount'      => 'required|numeric|min:0.01|max:99999999.99',
            // Farm date (Asia/Manila), same rule as the other logging screens.
            'date'        => 'required|date_format:Y-m-d|before_or_equal:' . ReportingDateService::reportingDateString(),
            'cage_id'     => 'nullable|exists:cages,id',
            'description' => 'nullable|string|max:1000',
        ], [
            'category.in'           => 'Choose a category that matches the transaction type.',
            'date.before_or_equal'  => 'The date cannot be in the future.',
        ]);
    }

    private function filtersFromRequest(Request $request): array
    {
        // Malformed dates are ignored instead of breaking the query.
        $date = fn ($v) => is_string($v) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? $v : null;

        $type = $request->query('type');
        $category = $request->query('category');

        return [
            'from'     => $date($request->query('from')),
            'to'       => $date($request->query('to')),
            'type'     => in_array($type, FinanceTransaction::TYPES, true) ? $type : null,
            'category' => in_array($category, FinanceTransaction::allCategories(), true) ? $category : null,
        ];
    }

    private function filteredQuery(array $filters)
    {
        return FinanceTransaction::query()
            ->when($filters['from'], fn ($q, $from) => $q->where('date', '>=', $from))
            ->when($filters['to'], fn ($q, $to) => $q->where('date', '<=', $to))
            ->when($filters['type'], fn ($q, $type) => $q->where('type', $type))
            ->when($filters['category'], fn ($q, $category) => $q->where('category', $category));
    }
}

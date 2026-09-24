@extends('layouts.app')
@section('title', 'Finance')

@section('content')
@php
    $peso = fn ($v) => '₱' . number_format(abs((float) $v), 2);
    $moneyValue = fn ($v, $negative = false) => '<span style="font-size:26px">' . ($negative ? '-' : '') . e($peso($v)) . '</span>';
    $categories = \App\Models\FinanceTransaction::CATEGORIES;
    $today = \App\Services\ReportingDateService::reportingDateString();
    $hasFilters = collect($filters)->filter()->isNotEmpty();
    // When an edit fails validation, old() holds the edit modal's input; keep it
    // out of the "Record Transaction" form.
    $editFailed = (bool) session('reopen_edit_finance');
    $addOld = fn ($key, $default = null) => $editFailed ? $default : old($key, $default);
    $inputClass = 'w-full border border-[#D9D9D9] rounded-lg px-3 py-2.5 text-sm text-[#333333] bg-white focus:outline-none focus:ring-2 focus:ring-[#102A4C]/30 focus:border-[#102A4C]';
    $filterClass = 'w-full min-w-0 sm:w-auto border border-[#D9D9D9] rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#102A4C]/30 focus:border-[#102A4C]';
@endphp
<div class="space-y-5">

    <x-page-header title="Finance" subtitle="Farm income, expenses, and net balance" />

    {{-- ── Summary ── --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
        <x-kpi-card label="Total Income" icon="trending-up" cardGradient="linear-gradient(135deg,#16a34a,#2D7D46)" delay="0ms"
                    :value="$moneyValue($totalIncome)">
            <div class="text-xs mt-1.5 font-medium" style="color: rgba(255,255,255,0.85);">{{ $hasFilters ? 'For the selected filters' : 'All recorded income' }}</div>
        </x-kpi-card>
        <x-kpi-card label="Total Expenses" icon="trending-down" cardGradient="linear-gradient(135deg,#dc2626,#9b1c24)" delay="60ms"
                    :value="$moneyValue($totalExpense)">
            <div class="text-xs mt-1.5 font-medium" style="color: rgba(255,255,255,0.85);">{{ $hasFilters ? 'For the selected filters' : 'All recorded expenses' }}</div>
        </x-kpi-card>
        <x-kpi-card label="Net Balance" icon="wallet"
                    cardGradient="{{ $net >= 0 ? 'linear-gradient(135deg,#0075de,#1D4E8F)' : 'linear-gradient(135deg,#dc2626,#9b1c24)' }}" delay="120ms"
                    :value="$moneyValue($net, $net < 0)">
            <div class="text-xs mt-1.5 font-medium" style="color: rgba(255,255,255,0.85);">{{ $net >= 0 ? 'Income minus expenses' : 'Expenses are higher than income' }}</div>
        </x-kpi-card>
    </div>

    {{-- ── Filters ── --}}
    <x-card padding="p-4">
        <form method="GET" action="{{ route('finance.index') }}" class="grid grid-cols-2 sm:flex sm:flex-wrap sm:items-end sm:gap-x-4 sm:gap-y-3 gap-3">
            <div>
                <label for="financeFilterFrom" class="block text-xs tracking-wider text-[#6B7280] mb-1.5">FROM</label>
                <input type="date" id="financeFilterFrom" name="from" value="{{ $filters['from'] }}" max="{{ $today }}" class="{{ $filterClass }}">
            </div>
            <div>
                <label for="financeFilterTo" class="block text-xs tracking-wider text-[#6B7280] mb-1.5">TO</label>
                <input type="date" id="financeFilterTo" name="to" value="{{ $filters['to'] }}" max="{{ $today }}" class="{{ $filterClass }}">
            </div>
            <div>
                <label for="financeFilterType" class="block text-xs tracking-wider text-[#6B7280] mb-1.5">TYPE</label>
                <select id="financeFilterType" name="type" class="{{ $filterClass }}">
                    <option value="">All Types</option>
                    <option value="income" @selected($filters['type'] === 'income')>Income</option>
                    <option value="expense" @selected($filters['type'] === 'expense')>Expense</option>
                </select>
            </div>
            <div>
                <label for="financeFilterCategory" class="block text-xs tracking-wider text-[#6B7280] mb-1.5">CATEGORY</label>
                <select id="financeFilterCategory" name="category" class="{{ $filterClass }}">
                    <option value="">All Categories</option>
                    @foreach($categories as $type => $list)
                    <optgroup label="{{ ucfirst($type) }}">
                        @foreach($list as $cat)
                        <option value="{{ $cat }}" @selected($filters['category'] === $cat)>{{ $cat }}</option>
                        @endforeach
                    </optgroup>
                    @endforeach
                </select>
            </div>
            <div class="col-span-2 sm:col-span-1 flex items-center justify-end gap-2">
                <x-button type="submit">
                    <i data-lucide="filter" class="w-4 h-4"></i> Apply
                </x-button>
                @if($hasFilters)
                <a href="{{ route('finance.index') }}"
                   class="px-4 py-2 text-xs font-medium rounded-lg border border-[#D9D9D9] text-[#6B7280] hover:bg-[#F5F6F8] transition-colors">
                    Reset
                </a>
                @endif
            </div>
        </form>
    </x-card>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-4">

        {{-- ── Record Transaction ── --}}
        <div class="bg-white rounded-lg border border-[#D9D9D9] p-5">
            <h2 class="text-sm font-medium text-[#333333] mb-4 flex items-center gap-2">
                <i data-lucide="plus-circle" class="w-4 h-4 text-[#6B7280]"></i>
                Record Transaction
            </h2>
            <form method="POST" action="{{ route('finance.store') }}" class="space-y-4" data-finance-form>
                @csrf
                <div>
                    <label for="financeAddType" class="block text-xs tracking-wider text-[#6B7280] mb-1.5">TYPE <span class="text-red-500">*</span></label>
                    <select name="type" id="financeAddType" required data-finance-type class="{{ $inputClass }}">
                        <option value="">Select type…</option>
                        <option value="income" @selected($addOld('type') === 'income')>Income</option>
                        <option value="expense" @selected($addOld('type') === 'expense')>Expense</option>
                    </select>
                    @unless($editFailed)<x-input-error name="type" />@endunless
                </div>
                <div>
                    <label for="financeAddCategory" class="block text-xs tracking-wider text-[#6B7280] mb-1.5">CATEGORY <span class="text-red-500">*</span></label>
                    <select name="category" id="financeAddCategory" required data-finance-category class="{{ $inputClass }}">
                        <option value="">Select category…</option>
                        @foreach($categories as $type => $list)
                        <optgroup label="{{ ucfirst($type) }}" data-type="{{ $type }}">
                            @foreach($list as $cat)
                            <option value="{{ $cat }}" @selected($addOld('category') === $cat)>{{ $cat }}</option>
                            @endforeach
                        </optgroup>
                        @endforeach
                    </select>
                    @unless($editFailed)<x-input-error name="category" />@endunless
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label for="financeAddAmount" class="block text-xs tracking-wider text-[#6B7280] mb-1.5">AMOUNT (₱) <span class="text-red-500">*</span></label>
                        <input type="number" name="amount" id="financeAddAmount" step="0.01" min="0.01" max="99999999.99" required
                               value="{{ $addOld('amount') }}" placeholder="0.00" class="{{ $inputClass }}">
                        @unless($editFailed)<x-input-error name="amount" />@endunless
                    </div>
                    <div>
                        <label for="financeAddDate" class="block text-xs tracking-wider text-[#6B7280] mb-1.5">DATE <span class="text-red-500">*</span></label>
                        <input type="date" name="date" id="financeAddDate" required max="{{ $today }}"
                               value="{{ $addOld('date', $today) }}" class="{{ $inputClass }}">
                        @unless($editFailed)<x-input-error name="date" />@endunless
                    </div>
                </div>
                <div>
                    <label for="financeAddCage" class="block text-xs tracking-wider text-[#6B7280] mb-1.5">CAGE <span class="normal-case tracking-normal text-[#9CA3AF]">(optional)</span></label>
                    <select name="cage_id" id="financeAddCage" class="{{ $inputClass }}">
                        <option value="">Whole farm</option>
                        @foreach($cages as $cage)
                        <option value="{{ $cage->id }}" @selected((string) $addOld('cage_id') === (string) $cage->id)>{{ $cage->cage_code }}</option>
                        @endforeach
                    </select>
                    @unless($editFailed)<x-input-error name="cage_id" />@endunless
                </div>
                <div>
                    <label for="financeAddDescription" class="block text-xs tracking-wider text-[#6B7280] mb-1.5">DESCRIPTION <span class="normal-case tracking-normal text-[#9CA3AF]">(optional)</span></label>
                    <textarea name="description" id="financeAddDescription" rows="2" maxlength="1000"
                              placeholder="e.g. 10 sacks of layer feed from supplier"
                              class="{{ $inputClass }} resize-none">{{ $addOld('description') }}</textarea>
                    @unless($editFailed)<x-input-error name="description" />@endunless
                </div>
                <x-button type="submit" class="w-full py-2.5">
                    Save Transaction
                </x-button>
            </form>
        </div>

        {{-- ── Breakdown by Category ── --}}
        <div class="xl:col-span-2 bg-white rounded-lg border border-[#D9D9D9] p-5">
            <h2 class="text-sm font-medium text-[#333333] mb-1">Breakdown by Category</h2>
            <p class="text-xs text-[#6B7280] mb-4">{{ $hasFilters ? 'For the selected filters.' : 'All recorded transactions.' }}</p>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                @foreach(['income' => ['Income', $totalIncome, '#1f6b3a'], 'expense' => ['Expenses', $totalExpense, '#9b1c24']] as $type => [$title, $typeTotal, $color])
                <div>
                    <div class="text-xs font-semibold tracking-wider uppercase mb-2" style="color: {{ $color }};">{{ $title }}</div>
                    @php $rows = $breakdown->get($type, collect()); @endphp
                    @if($rows->isEmpty())
                    <div class="rounded-lg border border-dashed border-[#D9D9D9] py-6 text-center text-xs text-[#9CA3AF]">No {{ strtolower($title) }} recorded.</div>
                    @else
                    <table class="w-full text-sm">
                        <tbody>
                            @foreach($rows as $row)
                            @php $share = $typeTotal > 0 ? $row->total / $typeTotal * 100 : 0; @endphp
                            <tr class="{{ $loop->last ? '' : 'border-b border-[#F0F0F0]' }}">
                                <td class="py-2 pr-2 text-[#333333]">
                                    {{ $row->category }}
                                    <span class="text-xs text-[#9CA3AF]">· {{ $row->entries }} {{ \Illuminate\Support\Str::plural('entry', $row->entries) }}</span>
                                </td>
                                <td class="py-2 pr-2 text-right font-medium whitespace-nowrap text-[#1f1f1f]">{{ $peso($row->total) }}</td>
                                <td class="py-2 text-right text-xs whitespace-nowrap text-[#6B7280]">{{ number_format($share, 1) }}%</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                    @endif
                </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- ── Transactions ── --}}
    <div class="bg-white rounded-lg border border-[#D9D9D9] overflow-hidden">
        <div class="px-5 py-3 border-b border-[#D9D9D9] flex items-center justify-between">
            <h2 class="text-sm font-medium text-[#333333]">Transactions</h2>
            <span class="text-xs text-[#6B7280]">{{ $transactions->total() }} {{ \Illuminate\Support\Str::plural('record', $transactions->total()) }}</span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-xs tracking-wider uppercase" style="background:#F5F6F8;color:#6B7280;">
                        <th class="px-5 py-3 font-medium whitespace-nowrap">Date</th>
                        <th class="px-5 py-3 font-medium">Type</th>
                        <th class="px-5 py-3 font-medium">Category</th>
                        <th class="px-5 py-3 font-medium">Cage</th>
                        <th class="px-5 py-3 font-medium">Description</th>
                        <th class="px-5 py-3 font-medium text-right whitespace-nowrap">Amount (₱)</th>
                        <th class="px-5 py-3 font-medium">Recorded By</th>
                        <th class="px-5 py-3 font-medium text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($transactions as $txn)
                    <tr class="border-t border-[#F0F0F0]">
                        <td class="px-5 py-3 whitespace-nowrap font-mono text-[#333333]">{{ $txn->date->format('m/d/Y') }}</td>
                        <td class="px-5 py-3">
                            <span class="px-2 py-0.5 rounded-full text-xs font-medium"
                                  style="background-color: {{ $txn->isIncome() ? '#e8f5ec' : '#fbe4e6' }}; color: {{ $txn->isIncome() ? '#1f6b3a' : '#9b1c24' }};">
                                {{ $txn->isIncome() ? 'Income' : 'Expense' }}
                            </span>
                        </td>
                        <td class="px-5 py-3 text-[#333333]">{{ $txn->category }}</td>
                        <td class="px-5 py-3 text-[#6B7280]">{{ $txn->cage?->cage_code ?? 'Whole farm' }}</td>
                        <td class="px-5 py-3 text-[#6B7280] break-words" style="max-width: 20rem;">{{ $txn->description ?: '—' }}</td>
                        <td class="px-5 py-3 text-right font-semibold whitespace-nowrap" style="color: {{ $txn->isIncome() ? '#1f6b3a' : '#9b1c24' }};">
                            {{ $txn->isIncome() ? '+' : '-' }}{{ $peso($txn->amount) }}
                        </td>
                        <td class="px-5 py-3 text-[#6B7280] whitespace-nowrap">{{ $txn->recorder?->name ?? '—' }}</td>
                        <td class="px-5 py-3">
                            <div class="flex items-center justify-end gap-1">
                                <button type="button"
                                        onclick="openFinanceEdit({{ Js::from([
                                            'id' => $txn->id,
                                            'type' => $txn->type,
                                            'category' => $txn->category,
                                            'amount' => $txn->amount,
                                            'date' => $txn->date->toDateString(),
                                            'cage_id' => $txn->cage_id,
                                            'description' => $txn->description,
                                        ]) }})"
                                        class="p-1.5 rounded hover:bg-black/5 transition-colors" style="color: #615d59;" aria-label="Edit transaction">
                                    <i data-lucide="pencil" class="w-3.5 h-3.5"></i>
                                </button>
                                @can('admin')
                                <form method="POST" action="{{ route('finance.destroy', $txn) }}"
                                      data-confirm="Delete this transaction?" data-confirm-action="Delete" data-confirm-severity="destructive">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="p-1.5 rounded hover:bg-red-50 transition-colors" style="color: #a39e98;" aria-label="Delete transaction">
                                        <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                                    </button>
                                </form>
                                @endcan
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="p-10 text-center text-sm text-[#9CA3AF]">
                            {{ $hasFilters ? 'No transactions match the selected filters.' : 'No transactions recorded yet. Use the form above to add the first one.' }}
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <x-paginator :paginator="$transactions" />
    </div>

    {{-- ── Edit Transaction Modal ── --}}
    <div id="financeEditModal" data-modal data-close="closeFinanceEdit" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4" role="dialog" aria-modal="true" style="display: none;">
        <div class="absolute inset-0" style="background-color: rgba(0,0,0,0.35); backdrop-filter: blur(4px);" onclick="closeFinanceEdit()"></div>
        <div class="relative w-full max-w-md rounded-2xl p-6 max-h-screen max-h-[100dvh] overflow-y-auto" style="background-color: #ffffff; box-shadow: rgba(0,0,0,0.01) 0 0.175px 1.041px, rgba(0,0,0,0.02) 0 0 0.8px 2.925px, rgba(0,0,0,0.027) 0 2.025px 7.847px, rgba(0,0,0,0.04) 0 4px 18px, rgba(0,0,0,0.05) 0 23px 52px;">
            <div class="flex items-center justify-between mb-5">
                <h2 class="text-[20px] font-semibold leading-[1.4] tracking-[-0.125px]" style="color: #1f1f1f;">Edit Transaction</h2>
                <button type="button" onclick="closeFinanceEdit()" class="p-1.5 rounded-full hover:bg-black/5 transition-colors" aria-label="Close">
                    <i data-lucide="x" class="w-5 h-5" style="color: #615d59;"></i>
                </button>
            </div>
            <form id="financeEditForm" method="POST" action="" class="space-y-4" data-finance-form>
                @csrf
                @method('PUT')
                <div>
                    <label for="financeEditType" class="block text-xs tracking-wider text-[#6B7280] mb-1.5">TYPE</label>
                    <select name="type" id="financeEditType" required data-finance-type class="{{ $inputClass }}">
                        <option value="income">Income</option>
                        <option value="expense">Expense</option>
                    </select>
                    @if($editFailed)<x-input-error name="type" />@endif
                </div>
                <div>
                    <label for="financeEditCategory" class="block text-xs tracking-wider text-[#6B7280] mb-1.5">CATEGORY</label>
                    <select name="category" id="financeEditCategory" required data-finance-category class="{{ $inputClass }}">
                        <option value="">Select category…</option>
                        @foreach($categories as $type => $list)
                        <optgroup label="{{ ucfirst($type) }}" data-type="{{ $type }}">
                            @foreach($list as $cat)
                            <option value="{{ $cat }}">{{ $cat }}</option>
                            @endforeach
                        </optgroup>
                        @endforeach
                    </select>
                    @if($editFailed)<x-input-error name="category" />@endif
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label for="financeEditAmount" class="block text-xs tracking-wider text-[#6B7280] mb-1.5">AMOUNT (₱)</label>
                        <input type="number" name="amount" id="financeEditAmount" step="0.01" min="0.01" max="99999999.99" required class="{{ $inputClass }}">
                        @if($editFailed)<x-input-error name="amount" />@endif
                    </div>
                    <div>
                        <label for="financeEditDate" class="block text-xs tracking-wider text-[#6B7280] mb-1.5">DATE</label>
                        <input type="date" name="date" id="financeEditDate" required max="{{ $today }}" class="{{ $inputClass }}">
                        @if($editFailed)<x-input-error name="date" />@endif
                    </div>
                </div>
                <div>
                    <label for="financeEditCage" class="block text-xs tracking-wider text-[#6B7280] mb-1.5">CAGE</label>
                    <select name="cage_id" id="financeEditCage" class="{{ $inputClass }}">
                        <option value="">Whole farm</option>
                        @foreach($cages as $cage)
                        <option value="{{ $cage->id }}">{{ $cage->cage_code }}</option>
                        @endforeach
                    </select>
                    @if($editFailed)<x-input-error name="cage_id" />@endif
                </div>
                <div>
                    <label for="financeEditDescription" class="block text-xs tracking-wider text-[#6B7280] mb-1.5">DESCRIPTION</label>
                    <textarea name="description" id="financeEditDescription" rows="2" maxlength="1000" class="{{ $inputClass }} resize-none"></textarea>
                    @if($editFailed)<x-input-error name="description" />@endif
                </div>
                <div class="flex gap-3 pt-1">
                    <button type="button" onclick="closeFinanceEdit()"
                            class="flex-1 py-2.5 text-sm font-medium rounded-lg transition-colors"
                            style="color: #1f1f1f; border: 1px solid #e6e6e6;"
                            onmouseover="this.style.backgroundColor='#f6f5f4'"
                            onmouseout="this.style.backgroundColor='transparent'">
                        Cancel
                    </button>
                    <x-button type="submit" class="flex-1 py-2.5">Save Changes</x-button>
                </div>
            </form>
        </div>
    </div>

@if($editFailed)
@php $editFinance = \App\Models\FinanceTransaction::find(session('reopen_edit_finance')); @endphp
@if($editFinance)
<x-modal-reopen modal-id="financeEditModal" session-key="reopen_edit_finance" guard="editFinance">
    openFinanceEdit({{ Js::from([
        'id' => $editFinance->id,
        'type' => old('type', $editFinance->type),
        'category' => old('category', $editFinance->category),
        'amount' => old('amount', $editFinance->amount),
        'date' => old('date', $editFinance->date->toDateString()),
        'cage_id' => old('cage_id', $editFinance->cage_id),
        'description' => old('description', $editFinance->description),
    ]) }});
</x-modal-reopen>
@endif
@endif

</div>

<script>
// Only the categories that belong to the chosen type can be picked (the
// server checks this too). Without JavaScript every category stays visible.
function syncFinanceCategories(form) {
    var typeSel = form.querySelector('[data-finance-type]');
    var catSel = form.querySelector('[data-finance-category]');
    if (!typeSel || !catSel) return;
    var type = typeSel.value;
    catSel.querySelectorAll('optgroup').forEach(function (group) {
        var match = !type || group.getAttribute('data-type') === type;
        group.hidden = !match;
        group.disabled = !match;
    });
    var chosen = catSel.options[catSel.selectedIndex];
    if (chosen && chosen.value && chosen.parentNode.disabled) catSel.value = '';
}

function openFinanceEdit(txn) {
    var form = document.getElementById('financeEditForm');
    form.action = '/finance/' + txn.id;
    document.getElementById('financeEditType').value = txn.type;
    syncFinanceCategories(form);
    document.getElementById('financeEditCategory').value = txn.category;
    document.getElementById('financeEditAmount').value = txn.amount;
    document.getElementById('financeEditDate').value = txn.date;
    document.getElementById('financeEditCage').value = txn.cage_id === null || txn.cage_id === undefined ? '' : String(txn.cage_id);
    document.getElementById('financeEditDescription').value = txn.description || '';
    document.getElementById('financeEditModal').style.display = 'flex';
    if (window.lucide) lucide.createIcons();
}

function closeFinanceEdit() {
    var modal = document.getElementById('financeEditModal');
    if (modal) modal.style.display = 'none';
}

(function () {
    document.querySelectorAll('form[data-finance-form]').forEach(function (form) {
        syncFinanceCategories(form);
        var typeSel = form.querySelector('[data-finance-type]');
        if (typeSel) typeSel.addEventListener('change', function () { syncFinanceCategories(form); });
    });
})();
</script>
@endsection
